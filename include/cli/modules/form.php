<?php

class FormManager extends Module {
    var $prologue = 'CLI form manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import forms from CSV or YAML file',
                'export' => 'Export forms from the system to CSV',
                'list' => 'List forms based on search criteria',
            ),
        ),
    );

    var $options = array(
        'file' => array('-f', '--file', 'metavar'=>'path',
            'help' => 'File or stream to process'),
        'verbose' => array('-v', '--verbose', 'default'=>false,
            'action'=>'store_true', 'help' => 'Be more verbose'),
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

          //create forms
          $errors = array();
          foreach ($data as $o) {
              if ('self::create' && is_callable('self::create'))
                  @call_user_func_array('self::create', array($o, &$errors, true));
              // TODO: Add a warning to the success page for errors
              //       found here
              $errors = array();
          }
            break;

        case 'export':
            if ($options['yaml']) {
              //get the agents
              $forms = $this->getQuerySet($options);

              //format the array nicely
              foreach ($forms as $F) {
                $clean[] = array(
                  'pid' => $F->get('pid'), 'type' => $F->get('type'), 'flags' => $F->get('flags'), 'title' => $F->getTitle(),
                  'instructions' => $F->getInstructions(), 'name' => $F->get('name'), 'notes' => $F->get('notes'),
                  'created' => $F->get('created'), 'updated' => $F->get('updated'));
              }

              //export yaml file
              // echo (Spyc::YAMLDump($clean));

              if(!file_exists('form.yaml')) {
                $fh = fopen('form.yaml', 'w');
                fwrite($fh, (Spyc::YAMLDump($clean)));
                fclose($fh);
              }
            }
            else {
              $stream = $options['file'] ?: 'php://stdout';
              if (!($this->stream = fopen($stream, 'c')))
                  $this->fail("Unable to open output file [{$options['file']}]");

              fputcsv($this->stream, array('PID', 'Type', 'Flags', 'Title', 'Instructions', 'Name', 'Notes', 'Created', 'Updated'));
              foreach (DynamicForm::objects() as $F)
                  fputcsv($this->stream,
                          array((string) $F->get('pid'), $F->get('type'), $F->get('flags'), $F->getTitle(), $F->getInstructions(),
                          $F->get('name'), $F->get('notes'), $F->get('created'), $F->get('updated')));
            }
            break;

        case 'list':
            $forms = $this->getQuerySet($options);

            foreach ($forms as $F) {
                $this->stdout->write(sprintf(
                    "%d %s %s %s %s %s %s %s %s\n",
                    $F->get('pid'), $F->get('type'), $F->get('flags'), $F->getTitle(), $F->getInstructions(),
                    $F->get('name'), $F->get('notes'), $F->get('created'), $F->get('updated')
                ));
            }
            break;

        default:
            $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }

    function getQuerySet($options, $requireOne=false) {
        $forms = DynamicForm::objects();

        return $forms;
    }

    static function create($vars=false) {
        $form = new DynamicForm($vars);
        $form->save();

        return $form;
    }
}
Module::register('form', 'FormManager');
?>
