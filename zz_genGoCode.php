<?php
// run this in Magento2 root folder
// Copyright Cyrill Schumacher
// Quick hack for one time use.

require('lib/internal/Magento/Framework/Xml/Parser.php');

@mkdir('zcode', 0700);

foreach (glob("app/code/Magento/*/etc/adminhtml/system.xml") as $systemXML) {
    $module = preg_replace('(app/code/Magento/(.*)/etc/adminhtml/system.xml)', '$1', $systemXML);
    print "==== $module ====\n\n";
    $xml = simplexml_load_file($systemXML);
    $sections = [];
    $moduleDefaultConfig = getDefaultConfig(str_replace('adminhtml/system.xml', 'config.xml', $systemXML));
    $moduleDefaultConfigFlat = getDefaultConfigFlat($moduleDefaultConfig);

    foreach ($xml as $key => $value) {
        foreach ($value->section as $section) {
            $tplSection = section($section);
            $groups = [];
            foreach ($section->group as $group) {

                // @todo recursive groups with config_path in field's
                // for brain tree i've done merging manually
//                if(isset($group->group)){
//                    var_dump(count($group->group));
//                    exit;
//                }

                $tplGroup = group($group);
                $fields = [];
                foreach ($group->field as $field) {
                    $fields[] = field(
                        $field,
                        $module,
                        $section->attributes()->id,
                        $group->attributes()->id,
                        $moduleDefaultConfig,
                        $moduleDefaultConfigFlat
                    );
                }
                $tplGroup = str_replace('{{fields}}', implode("\n", $fields), $tplGroup);
                $groups[] = $tplGroup;
            }
            $tplSection = str_replace('{{groups}}', implode("\n", $groups), $tplSection);
            $sections[] = $tplSection;
        }
    }

    $tier3 = getDefaultConfig3Tier($moduleDefaultConfigFlat);
    if (count($tier3) > 0) {
        $sections[] = "\n// Hidden Configuration, may be visible somewhere else ...\n";
    }
    foreach ($tier3 as $sectionID => $group) {
        $tplSection = sectionHidden($sectionID);
        $groups = [];
        foreach ($group as $groupID => $field) {
            $tplGroup = groupHidden($groupID);
            $fields = [];
            foreach ($field as $fieldID => $value) {
                $fields[] = fieldHidden($sectionID, $groupID, $fieldID, $value);
            }
            $tplGroup = str_replace('{{fields}}', implode("\n", $fields), $tplGroup);
            $groups[] = $tplGroup;
        }
        $tplSection = str_replace('{{groups}}', implode("\n", $groups), $tplSection);
        $sections[] = $tplSection;
    }

    $all = implode('', $sections);
    $sm = strtolower($module);
    file_put_contents("zcode/config_$sm.go", '
// +build ignore

package ' . $sm . '

import "github.com/corestoreio/csfw/config"

var PackageConfiguration = config.NewConfiguration(' . "\n$all" . ')
    ');
}

function getDefaultConfig($configXML) {
    if (!file_exists($configXML)) {
        return [];
    }
    $parser = new Magento\Framework\Xml\Parser();
    $parser->load($configXML);
    $arr = $parser->xmlToArray();
    if (isset($arr['config']['_value']['default'])) {
        return $arr['config']['_value']['default'];
    }
    return [];
}

function getDefaultConfigFlat(array $config) {
    $flat = [];
    foreach ($config as $p1 => $cg) {
        foreach ($cg as $p2 => $cf) {
            if (is_array($cf)) {
                foreach ($cf as $p3 => $value) {
                    $flat[$p1 . '/' . $p2 . '/' . $p3] = $value;
                }
            }
        }
    }
    return $flat;
}

function getDefaultConfig3Tier(array $config) {
    $ret = [];
    foreach ($config as $path => $value) {
        $t = explode('/', $path);
        $ret[$t[0]][$t[1]][$t[2]] = $value;
    }
    return $ret;
}

function fieldHidden($sectionID, $groupID, $fieldID, $default) {

    if (is_numeric($default)) {
        $intDefault = (int)$default;
        if ($intDefault === 1 || $intDefault === 0) {
            $default = $intDefault === 1 ? 'true' : 'false';
        }
    } elseif (empty($default)) {
        $default = 'nil';
    } elseif (is_array($default)) {
        $default = '`' . json_encode($default) . '`';
    } else {
        $default = "`$default`";
    }
    return sprintf('&config.Field{
			// Path: `%s`,
			ID:      "%s",
			Type:     config.TypeHidden,
			Visible: config.VisibleNo,
			Scope:   config.NewScopePerm(config.ScopeDefaultID), // @todo search for that
			Default: %s,
		    },
    ',
        $sectionID . '/' . $groupID . '/' . $fieldID,
        $fieldID,
        $default
    );
}

function field(SimpleXMLElement $f, $module, $sID, $gID, array $moduleDefaultConfig, array &$moduleDefaultConfigFlat) {
    $default = '';
    $backendModel = 'nil,';
    $sourceModel = 'nil,';

    if ($f->backend_model) {
        $backendModel .= ' // ' . $f->backend_model;
    }
    if ($f->source_model) {
        $sourceModel .= ' // ' . $f->source_model;
    }

    $path = $sID . '/' . $gID . '/' . $f->attributes()->id;
    if (isset($moduleDefaultConfig[(string)$sID])) {
        $sec = @$moduleDefaultConfig[(string)$sID];
        $grou = @$sec[(string)$gID];
        $default = @$grou[(string)$f->attributes()->id];
    }

    $type = 'config.Type' . ucfirst($f->attributes()->type);
    if (strpos($type, '\\') !== false) {
        $type = 'config.TypeCustom, // @todo: ' . ucfirst($f->attributes()->type);
    }

    if (is_numeric($default)) {
        $intDefault = (int)$default;
        if ($type === 'config.TypeSelect' && ($intDefault === 1 || $intDefault === 0)) {
            $default = $intDefault === 1 ? 'true' : 'false';
        }
        unset($moduleDefaultConfigFlat[$path]);
    } elseif (empty($default)) {
        $default = 'nil';
    } elseif (is_array($default)) {
        if (isset($default['_attribute']['backend_model'])) {
            $backendModel .= ' // @todo ' . $default['_attribute']['backend_model'];
            $default = 'nil';
        } elseif (isset($default['_attribute']['source_model'])) {
            $sourceModel .= ' // @todo ' . $default['_attribute']['source_model'];
            $default = 'nil';
        } else {
            $default = '`' . json_encode($default) . '`';
        }
        unset($moduleDefaultConfigFlat[$path]);
    } else {
        $default = "`$default`";
        unset($moduleDefaultConfigFlat[$path]);
    }

    return sprintf('&config.Field{
			// Path: `%s`,
			ID:      "%s",
			Label:   `%s`,
			Comment: `%s`,
			Type:     %s,
			SortOrder: %d,
			Visible: config.VisibleYes,
			Scope:   %s,
			Default: %s,
			BackendModel: %s
			SourceModel: %s
		    },
    ',
        $path,
        $f->attributes()->id,
        $f->label,
        trim($f->comment),
        $type,
        (int)$f->attributes()->sortOrder,
        scope($f),
        $default,
        $backendModel,
        $sourceModel
    );
}

function group(SimpleXMLElement $g) {
    return sprintf('&config.Group{
				ID:    "%s",
				Label: `%s`,
				Comment: `%s`,
				SortOrder: %d,
				Scope: %s,
				Fields: config.FieldSlice{
				    {{fields}}
				},
			},
    ',
        $g->attributes()->id,
        $g->label,
        trim($g->comment),
        (int)$g->attributes()->sortOrder,
        scope($g)
    );
}


function groupHidden($id) {
    return sprintf('&config.Group{
				ID:    "%s",
				Fields: config.FieldSlice{
				    {{fields}}
				},
			},
    ',
        $id
    );
}

function section(SimpleXMLElement $s) {

    return sprintf('&config.Section{
		ID: "%s",
		Label: "%s",
		SortOrder: %d,
		Scope: %s,
		Groups: config.GroupSlice{
		    {{groups}}
		},
	},
    ',
        $s->attributes()->id,
        $s->label,
        (int)$s->attributes()->sortOrder,
        scope($s)
    );
}

function sectionHidden($id) {
    return sprintf('&config.Section{
		ID: "%s",
		Groups: config.GroupSlice{
		    {{groups}}
		},
	},
    ',
        $id
    );
}

function scope(SimpleXMLElement $s) {
    $scope = [];
    if ((string)$s->attributes()->showInDefault === '1') {
        $scope[] = 'config.ScopeDefaultID';
    }
    if ((string)$s->attributes()->showInWebsite === '1') {
        $scope[] = 'config.ScopeWebsiteID';
    }
    if ((string)$s->attributes()->showInStore === '1') {
        $scope[] = 'config.ScopeStoreID';
    }
    if (count($scope) === 3) {
        return 'config.ScopePermAll';
    }
    if (count($scope) < 1) {
        return 'nil';
    }
    return 'config.NewScopePerm(' . implode(',', $scope) . ')';
}