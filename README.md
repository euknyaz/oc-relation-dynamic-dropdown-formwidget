# Relation Dynamic Dropdown Form Widget

This is a developer plugin to add Relation Dynamic Dropdown functionality to backend forms. 

This plugin includes a **Relation Dynamic Dropdown Form Widget** that extends [relation form widget](https://octobercms.com/docs/backend/forms#widget-relation) and provides capabilities of dynamic search and selection of dropdown options for 'belongsTo' and 'hasOne' relations with new field type: **relation-dynamic-dropdown**, which could be used instead of **relation** field type.

## Advantages of this form widget

* Helps manage relation dropdown fields with a lot of associated records. 
* Prevents loading of all records as select options in form HTML page, which is a huge performance advantage in case of thousands of records.
* Provides dynamic search of records in dropdown with autocomplete functionality.
* Uses pagination in autocomplete search results, next page is loaded automatically on scroll to the bottonm of search results.
* Handles AJAX search requests automatically, all you have to do is switch filed type from 'relation' to 'relation-dynamic-dropdown'.

## Demo

![Demo](https://github.com/euknyaz/oc-relation-dynamic-dropdown-formwidget/raw/master/assets/github/demo.gif "Demo")

## Getting Started

Download from the October Marketplace or clone the [https://github.com/euknyaz/oc-relation-dynamic-dropdown-formwidget](repository) from github into your plugins folder.

### Installing

Simply install the plugin from the market place or clone the repository as mentioned in the Getting Started section.

You can then access the FormWidget in your model's fields.yaml file by used 'relation-dynamic-dropdown' as the field type.

```
# fields.yaml
user:
    label: User
    span: auto
    type: relation-dynamic-dropdown
    nameFrom: email
```

Here is an example of advanced configuration:
```
# fields.yaml
user:
    label: User
    comment: 'dynamic search and autocomplete with lazy loading'
    span: auto
    type: relation-dynamic-dropdown
    select: CONCAT(first_name, ' ', last_name, ' - ', email)
    limit: 10
    order: first_name # optional parameter
    scope: withAuthorRoles # optional parameter
    attributes:
        data-minimum-input-length: 2
        data-ajax--delay: 300
```
**Note**: You may use type: 'relation-dynamic-dropdown' for any relation fields defined as: 'belongsTo' or 'hasOne' in the model associated with form.


Option | Description
------------- | -------------
**nameFrom** | Field name to search and display in dropdown. Option inherited from Relation widget.
**select** | Dynamically generated field with raw SQL capabilities like SQL functions. The most useful is function: CONCAT(field1, ' ', field2, ...).
**limit** | Count of records to be displayed in search results. Default: 20.
**order** | Field name to sort search results. Optional parameter.
**scope** | This is an optional parameter to search results for dropdown values based on scope filter defined in relation model.
**attributes.data-minimum-input-length** | Count of records to load and display with dynamic dropdown. Default: 20.
**attributes.data-ajax--delay** | Delay between consequtive search ajax requests in milliseconds. Default: 300.

## How it works

Relation Dynamic Dropdown Form Widget extends Relation widget overrides functionality of "belongsTo" or "hasOne" relations, which displays "dropdown" form widget.

It sets "data-handler" attribute for select2 dropdown to "onRelationDropdownSearch" automactially, which turns select2 dropdown into search & autocomplete mode.

Embedded handler method "onRelationDropdownSearch" is used for AJAX requests for search & autocomplete functionality, this method pickups field configuration and renders data for you automcatically, by looking at field configuration parameters 'nameFrom' and 'select'.

**Note**: You can use HTML markup in your field rendering with configuration like "select: CONCAT('&lt;b&gt;', first_name, ' ', last_name, '&lt;/b&gt; - ', email)", and it's going to work fine in Update Form and Relation Dropdown widget, but it's not going to render HTML properly in Preview Form becuase of it's current limitations. 

## Built With

* [OctoberCMS](http://www.octobercms/)

## References

* [OctoberCMS Issue: Ajax options for backend dropdowns. #2722](https://github.com/octobercms/october/issues/2722) - Relation Dynamic Dropdown Form Widget simplifies approach described in this issue.
* [Select2 Documentation](https://select2.org/)

## Support

Feel free to use any of the following support options (**Plese don't use Reviews to request for support.**):

* [Plugin Support Forum](https://octobercms.com/plugin/support/euknyaz-relationdynamicdropdown)
* [Github Issues](https://github.com/euknyaz/oc-relation-dynamic-dropdown-formwidget/issues)

## Versioning

I use use [Github](http://github.com/) for versioning.

## Authors

* [Eugene Knyazkov](http://github.com/euknyaz)

## License

This project is licensed under the MIT License.

## Acknowledgments

* Thanks to the OctoberCMS community.
