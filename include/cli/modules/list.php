<?php
include_once INCLUDE_DIR .'class.translation.php';


class ListManager extends Module {
    var $prologue = 'CLI list manager';
    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import lists to the system',
                'export' => 'Export lists from the system',
                'show' => 'Show the lists',
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
                //create lists
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
                  $lists = $this->getQuerySet($options);

                  //format the array nicely
                  foreach ($lists as $L) {
                    $clean[] = array(
                      'name' => $L->getName(), 'name_plural' => $L->getPluralName(), 'sort_mode' => $L->getSortMode(),
                      'masks' => $L->get('masks'), 'type' => $L->get('type'), 'configuration'  => $L->get('configuration'),
                      'notes' => $L->get('notes'), 'created' => $L->get('created'), 'updated' => $L->get('updated'));
                  }

                  //export yaml file
                  // echo (Spyc::YAMLDump($clean));

                  if(!file_exists('list.yaml')) {
                    $fh = fopen('list.yaml', 'w');
                    fwrite($fh, (Spyc::YAMLDump($clean)));
                    fclose($fh);
                  }
                }
                else {
                  $stream = $options['file'] ?: 'php://stdout';
                  if (!($this->stream = fopen($stream, 'c')))
                      $this->fail("Unable to open output file [{$options['file']}]");

                  fputcsv($this->stream, array('Name', 'Plural Name', 'Sort Mode', 'Masks', 'Type', 'Configuration', 'Notes', 'Created', 'Updated'));
                  foreach (DynamicList::objects() as $L)
                      fputcsv($this->stream,
                              array((string) $L->getName(), $L->getPluralName(), $L->getSortMode(), $L->get('masks'), $L->get('type'),
                              $L->get('configuration'), $L->get('notes'), $L->get('created'), $L->get('updated')));
                }
                break;
            case 'show':
                $lists = DynamicList::objects()->order_by('-type', 'name');
                foreach ($lists as $list) {
                    $this->stdout->write(sprintf("%d %s \n",
                                $list->getId(),
                                $list->getName(),
                                $list->getPluralName() ?: $list->getName()
                                ));
                }
                break;
            default:
                $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }

    function getQuerySet($options, $requireOne=false) {
        $lists = DynamicList::objects();

        return $lists;
    }

    static function create($vars=false) {
        $list = new DynamicList($vars);
        return $list;
    }

    private function __create($vars, &$error=false, $fetch=false) {
        //see if list exists
        if ($fetch && ($listId=self::getIdByCombo($vars['name'], $vars['sort_mode'])))
          return DynamicList::lookup($fieldId);
        else {
          $listId = self::create($vars);
          $listId->save();
          return $listId->id;
        }
    }

    private function getIdByCombo($name, $sort_mode) {
      $row = DynamicList::objects()
          ->filter(array(
            'name'=>$name,
            'sort_mode'=>$sort_mode))
          ->values_flat('id')
          ->first();

      return $row ? $row[0] : 0;
    }
}
Module::register('list', 'ListManager');
?>
