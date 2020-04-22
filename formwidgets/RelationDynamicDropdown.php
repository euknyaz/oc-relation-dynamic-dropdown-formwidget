<?php

namespace Euknyaz\RelationDynamicDropdown\FormWidgets;

use Lang;
use ApplicationException;
use Illuminate\Support\Facades\DB;
use Backend\FormWidgets\Relation;
use October\Rain\Database\Relations\Relation as RelationBase;

/** 
 * Relation Form Widget to display dynamic dropdown powered by select2 & data-handler attribute.
 * 
 * Useful for forms with relation dropdowns, which have hunders or thousands of records.
 * Allows to avoid rendering hunderds/thousands of records as select options in HTML,
 * provides dynamic search and loading instead. 
 * 
 * Supports automatic paging of search results, on scroll to the bottom of dropdown list.
 * 
 * Please note: this widget uses search with SQL query 'field LIKE '%<search-keyword>%',
 * is the same approach as used by search control for list widget. 
 * Be aware that this approach could not use indecies, so it require tabel full-scan and could be slow. 
 * 
 * [fields.yaml]
 * user:
 *     label: User
 *     span: auto
 *     type: relation-dynamic-dropdown
 *     nameFrom: first_name 
 *     # or select: CONCAT(first_name, ' ', last_name, ' - ', email)
 *     scope: withPermissions # optional parameter if you want to filter dropdown values with scope filter
 *     attributes:
 *        data-minimum-input-length: 20 # cound of records to load with dynamic dropdown (default 20) 
 *        data-ajax--delay: 300 # time is milliseconds (default 300)
 */
class RelationDynamicDropdown extends Relation
{
    protected $defaultAlias = 'relation';

    /**
     * @var int default limit of records to be displayed in search results
     */
    public const DEFAULT_SEARCH_RECORDS_LIMIT = 20;

    /**
     * @var int default minimum input length to start searching
     */
    public const DEFAULT_MIN_INPUT_LENGTH = 1;

    /**
     * @var int default delay before request of search results with AJAX
     */
    public const DEFAULT_AJAX_DELAY = 300; 

    /*
     * Render the form widget with the initial relation partial
     * we had to override this method, because default render() is looking for partial in local folder
     */
    public function render()
    {
        $this->prepareVars();
        return $this->makePartial('~/modules/backend/formwidgets/relation/partials/_relation.htm');
    }

    /**
     * The exact same function as Relation::makeRenderFormField() with two customizations:
     * 1) automatic data-handler and data-attributes configuration
     * 2) reduce displayed dropdown options to selected value only
     */ 
    protected function makeRenderFormField()
    {
        list($model, $attribute) = $this->resolveModelAttribute($this->valueFrom);
        $relationType   = $model->getRelationType($attribute);
        $attributeValue = $this->formField->value;
        $valueFrom      = $this->valueFrom;

        // If relation widget has data-handler attribute, then render widget without quering database for options
        if (in_array($relationType, ['belongsTo', 'hasOne'])) {
            $formField = $this->formField;
            return $this->renderFormField = RelationBase::noConstraints(function () use($formField, $model, $attribute, $relationType, $attributeValue, $valueFrom) {
                $field = clone $formField;

                // CUSTOMIZATION: automatic data-handler and data-attributes configuration
                $field->type = 'dropdown';
                $field->attributes['field']['data-handler'] = "onRelationDropdownSearch";
                $field->attributes['field']['data-request-data'] = "_attribute: '{$valueFrom}'";
                $field->attributes['field']['data-minimum-input-length'] = $field->attributes['field']['data-minimum-input-length'] ?? self::DEFAULT_MIN_INPUT_LENGTH;
                $field->attributes['field']['data-ajax--delay'] = $field->attributes['field']['data-ajax--delay'] ?? self::DEFAULT_AJAX_DELAY;

                $relationObject = $this->getRelationObject();
                $query = $relationObject->newQuery();
                $relationModel = $model->makeRelation($attribute);

                // CUSTOMIZATION: reduce displayed dropdown options to selected value only
                // This allows us to avoid thousands of records flooding HTML DOM of form page 
                $query->where($relationModel->getKeyName(), $attributeValue);

                // Order query by the configured option.
                if ($this->order) {
                    // Using "raw" to allow authors to use a string to define the order clause.
                    $query->orderByRaw($this->order);
                }

                // It is safe to assume that if the model and related model are of
                // the exact same class, then it cannot be related to itself
                if ($model->exists && (get_class($model) == get_class($relationModel))) {
                    $query->where($relationModel->getKeyName(), '<>', $model->getKey());
                }

                if ($scopeMethod = $this->scope) {
                    $query->$scopeMethod($model);
                }

                // Even though "no constraints" is applied, belongsToMany constrains the query
                // by joining its pivot table. Remove all joins from the query.
                $query->getQuery()->getQuery()->joins = [];

                // Determine if the model uses a tree trait
                $treeTraits = ['October\Rain\Database\Traits\NestedTree', 'October\Rain\Database\Traits\SimpleTree'];
                $usesTree = count(array_intersect($treeTraits, class_uses($relationModel))) > 0;

                // The "sqlSelect" config takes precedence over "nameFrom".
                // A virtual column called "selection" will contain the result.
                // Tree models must select all columns to return parent columns, etc.
                if ($this->sqlSelect) {
                    $nameFrom = 'selection';
                    $selectColumn = $usesTree ? '*' : $relationModel->getKeyName();
                    $result = $query->select($selectColumn, DB::raw($this->sqlSelect . ' AS ' . $nameFrom));
                }
                else {
                    $nameFrom = $this->nameFrom;
                    $result = $query->getQuery()->get();
                }

                // Some simpler relations can specify a custom local or foreign "other" key,
                // which can be detected and implemented here automagically.
                $primaryKeyName = in_array($relationType, ['hasMany', 'belongsTo', 'hasOne'])
                    ? $relationObject->getOtherKey()
                    : $relationModel->getKeyName();

                $field->options = $usesTree
                    ? $result->listsNested($nameFrom, $primaryKeyName)
                    : $result->lists($nameFrom, $primaryKeyName);

                return $field;
            });

            } else {
                // In other case use default behaviour 
                return parent::makeRenderFormField();
            }
    }

    /**
     * onRelationDropdownSearch - Relation Dropdown Search AJAX hanlder
     *
     * @param $recordId 
     */
    public function onRelationDropdownSearch($recordId = null)
    {
        $searchQuery  = post('q');
        $relationAttr = post('_attribute'); // this is the name of relation field to select records
        $_type        = post('_type'); // 'query' or 'query:append'
        $page         = intVal(post('page', 1)); // page parameter for load-more requests

        list($model, $attribute) = $this->resolveModelAttribute($relationAttr);
        $relationModel = $model->makeRelation($attribute);
        $query = $relationModel->newQuery();
        $sqlSelect = $this->formField->config->select ?? null;

        // Now we need extract our params [limit, nameFrom, select] from $this->parentForm
        // because $this->sqlSelect, $this->nameFrom, $this->formField provides
        // incorrect values in case when there is more than 2 fields of the same type on the form
        $parentFormConfig = json_decode(json_encode($this->parentForm), true);
        $formFieldConfig  = $this->findFieldConfig($parentFormConfig, $relationAttr, 'relation-dynamic-dropdown');

        $limit       = intVal($formFieldConfig['limit'] ?? self::DEFAULT_SEARCH_RECORDS_LIMIT); // 20 records is default limit
        $nameFrom    = $formFieldConfig['nameFrom'] ?? $relationModel->getKeyName();
        $sqlSelect   = $formFieldConfig['select'] ?? null;
        $sqlOrder    = $formFieldConfig['order'] ?? null;
        $scopeMethod = $formFieldConfig['scope'] ?? null;
        $emptyOption = $formFieldConfig['emptyOption'] ?? null;

        // Order query by the configured option.
        if ($sqlOrder) {
            // Using "raw" to allow authors to use a string to define the order clause.
            $query->orderByRaw($sqlOrder);
        }
        if ($scopeMethod) {
            if (!method_exists($relationModel, 'scope'.ucfirst($scopeMethod))) {
                throw new ApplicationException(Lang::get('backend::lang.field.options_method_not_exists', [
                    'model' => get_class($relationModel), 
                    'method' => 'scope'.ucfirst($scopeMethod), 
                    'field' => $relationAttr
                ]));
            }
            $query->$scopeMethod($relationModel);
        }

        $records = [];
        if ($sqlSelect) {
            $nameFrom     = 'selection';
            $selectColumn = $relationModel->getKeyName();
            $searchFields = [$relationModel->getKeyName(), DB::raw($sqlSelect)];
            $records = $query->searchWhere($searchQuery, $searchFields)
                             ->offset(($page-1)*$limit)
                             ->take($limit)
                             ->select($selectColumn, DB::raw($sqlSelect . ' AS ' . $nameFrom))->get();
        }
        else {
            $searchFields = [$relationModel->getKeyName(), $nameFrom];
            $query->searchWhere($searchQuery, $searchFields)->offset(($page-1)*$limit)->take($limit);
            $records = $query->getQuery()->get();
        }

        $results = [];
        // Always display an empty option in autocomplete dropdown on the first page of results
        if(isset($emptyOption) && $page === 1) {
            $results[] = [ 'id' => '', 'text' => $emptyOption ];
        }
        foreach ($records as $record) {
            $results[] = [
                'id'   => $record->{$relationModel->getKeyName()},
                'text' => $record->{$nameFrom}, // could be rich text/html
            ];
        }

        $return = [ 'results' => $results ];
        if($records->count() == $limit)  $return['pagination'] = [ 'more' => true ];

        return $return;
    }

    /**
     * Find field config by recursive traversal of $this->parentForm object
     *
     * @param $data
     * @param $searchFieldName
     * @param $searchFieldName
     */
    private function findFieldConfig($data, $searchFieldName, $searchFiledType) {
        if(isset($data['fields']) && isset($data['fields'])) {
            foreach($data['fields'] as $fieldName => $fieldConfig) {
                if(isset($fieldConfig['type']) && $fieldConfig['type'] === $searchFiledType) {
                    if($sanitizedFieldName = explode('@', $fieldName)[0] ?? null) {
                        if($searchFieldName === $sanitizedFieldName) {
                            return $data['fields'][$fieldName];
                        }
                    }
                }
            }
        }
        if(is_array($data)) {
            foreach($data as $record) {
                $res = $this->findFieldConfig($record, $searchFieldName, $searchFiledType);
                if($res) return $res;
            }
        }
        return false;
    }
}
