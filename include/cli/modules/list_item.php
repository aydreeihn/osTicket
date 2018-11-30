<?php
class ListItemManager extends Module {
    var $prologue = 'CLI list item manager';
    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import list items to the system',
                'export' => 'Export list items from the system',
                'list' => 'List lists based on search criteria',
            ),
        ),
    );

    var $options = array(
        'file' => array('-f', '--file', 'metavar'=>'path',
            'help' => 'File or stream to process'),
        'verbose' => array('-v', '--verbose', 'default'=>false,
            'action'=>'store_true', 'help' => 'Be more verbose'),
        'id' => array('-ID', '--id', 'metavar'=>'id',
            'help' => 'List ID'),
        'csv' => array('-csv', '--csv', 'default'=>false,
            'action'=>'store_true', 'help'=>'Export in csv format'),
        'yaml' => array('-yaml', '--yaml', 'default'=>false,
            'action'=>'store_true', 'help'=>'Export or Import in yaml format'),
        );

    var $stream;

    function run($args, $options) {

        if (!function_exists('boolval')) {
          function boolval($val) {
            return (bool) $val;
          }
        }

        Bootstrap::connect();

        switch ($args['action']) {
            case 'import':
              // Properly detect Macintosh style line endings
              ini_set('auto_detect_line_endings', true);

              //check command line option
              if (!$options['file'] || $options['file'] == '-')
                $options['file'] = 'php://stdin';

              //make sure the file can be opened
              if (!($this->stream = fopen($options['file'], 'rb')))
                $this->fail("Unable to open input file [{$options['file']}]");

              //place file into array
              $data = YamlDataParser::load($options['file']);

              $errors = array();
              foreach ($data as $D) {
                //look up list id by name
                $list_id = DynamicList::getIdByName($D['list_name']);

                $D['list_id'] = $list_id;
                unset($D['list_name']);

                //create list items
                if ('self::__create' && is_callable('self::__create'))
                        @call_user_func_array('self::__create', array($D, &$errors, true));
                    // TODO: Add a warning to the success page for errors
                    //       found here
                    $errors = array();
              }
                break;
            case 'export':
                if ($options['yaml']) {
                  //get the agents
                  $listItems = $this->getQuerySet($options);

                  //format the array nicely
                  foreach ($listItems as $L) {
                    if (!$L->getList())
                        continue;
                    else
                        $clean[] = array(
                          'list_name' => $L->getList()->getName(), 'status' => $L->get('status'), 'value' => $L->getValue(),
                          'extra' => $L->get('extra'), 'sort' => $L->getSortOrder(), 'properties' => $L->get('properties'));
                  }

                  //export yaml file
                  // echo (Spyc::YAMLDump($clean));

                  if(!file_exists('list_item.yaml')) {
                    $fh = fopen('list_item.yaml', 'w');
                    fwrite($fh, (Spyc::YAMLDump($clean)));
                    fclose($fh);
                  }
                }
                else {
                  $stream = $options['file'] ?: 'php://stdout';
                  if (!($this->stream = fopen($stream, 'c')))
                      $this->fail("Unable to open output file [{$options['file']}]");

                  fputcsv($this->stream, array('List Name', 'Status', 'Value', 'Extra', 'Sort', 'Properties'));
                  foreach (DynamicListItem::objects() as $L)
                      fputcsv($this->stream,
                              array((string) $L->getList()->getName(), $L->get('status'), $L->getValue(), $L->get('extra'),
                                             $L->getSortOrder(), $L->get('properties')));
                }
                break;

            case 'list':
                $listItems = $this->getQuerySet($options);

                foreach ($listItems as $L) {
                    $this->stdout->write(sprintf(
                        "%s %s %s %s %s %s\n",
                        $L->getList()->getName(), $L->get('status'), $L->getValue(), $L->get('extra'), $L->getSortOrder(), $L->get('properties')
                    ));
                }
                break;
            default:
                $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }

    function getQuerySet($options, $requireOne=false) {
        $listItems = DynamicListItem::objects();

        return $listItems;
    }

    static function create($vars=false) {
        $itemId = new DynamicListItem($vars);
        return $itemId;
    }

    private function __create($vars, &$error=false, $fetch=false) {
        //see if list item exists
        if ($fetch && ($itemId=self::getIdByValue($vars['value'])))
          return DynamicListItem::lookup($fieldId);
        else {
          $itemId = self::create($vars);
          $itemId->save();
          return $itemId->id;
        }
    }

    private function getIdByValue($value) {
      $row = DynamicListItem::objects()
          ->filter(array(
            'value'=>$value))
          ->values_flat('id')
          ->first();

      return $row ? $row[0] : 0;
    }
}
Module::register('list_item', 'ListItemManager');
?>
