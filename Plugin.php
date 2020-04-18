<?php namespace Euknyaz\RelationDynamicDropdown;

use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name'        => 'euknyaz.relationdynamicdropdown::lang.plugin.name',
            'description' => 'euknyaz.relationdynamicdropdown::lang.plugin.description',
            'author'      => 'euknyaz.relationdynamicdropdown::lang.plugin.author',
            'icon'        => 'icon-check-square-o',
            'homepage'    => 'https://github.com/euknyaz/oc-relation-dynamic-dropdown-formwidget'
        ];
    }

    public function registerFormWidgets()
    {
        return [
            'Euknyaz\RelationDynamicDropdown\FormWidgets\RelationDynamicDropdown' => [
                'label' => 'Relation Dynamic Dropdown',
                'code'  => 'relation-dynamic-dropdown'
            ]
        ];
    }
}
