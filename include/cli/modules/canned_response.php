<?php

class CannedResponseManager extends Module {
    var $prologue = 'CLI canned response manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import canned responses from CSV or YAML file',
                'export' => 'Export canned responses from the system to CSV',
                'list' => 'List canned responses based on search criteria',
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

          $errors = array();
          foreach ($data as $D) {
            //look up dept id by title
            $dept_id = Dept::getIdByName($D['department']);

            $D['dept_id'] = $dept_id;
            unset($D['department']);

            //create canned responses
            if ('self::create' && is_callable('self::create'))
                @call_user_func_array('self::create', array($D, &$errors, true));
          }
            break;

        case 'export':
            if ($options['yaml']) {
              //get the agents
              $cannedResponses = $this->getQuerySet($options);

              //format the array nicely
              foreach ($cannedResponses as $C) {
                $clean[] = array('department' => Dept::getNameById($C->get('dept_id')), 'isenabled' => $C->isEnabled(), 'title' => $C->getTitle(), 'response' => $C->getResponse(),
                                 'lang' => $C->get('lang'), 'notes' => $C->getNotes(), 'created' => $C->get('created'), 'updated' => $C->get('updated'));
              }

              //export yaml file
              // echo (Spyc::YAMLDump($clean));

              if(!file_exists('canned_response.yaml')) {
                $fh = fopen('canned_response.yaml', 'w');
                fwrite($fh, (Spyc::YAMLDump($clean)));
                fclose($fh);
              }
            }
            else {
              $stream = $options['file'] ?: 'php://stdout';
              if (!($this->stream = fopen($stream, 'c')))
                  $this->fail("Unable to open output file [{$options['file']}]");

              fputcsv($this->stream, array('Department', 'isEnabled', 'Title', 'Response', 'Lang', 'Notes', 'Created', 'Updated'));
              foreach (Canned::objects() as $C)
                  fputcsv($this->stream,
                          array((string) Dept::getNameById($C->get('dept_id')), $C->isEnabled(), $C->getTitle(), $C->getResponse(), $C->get('lang'),
                          $C->getNotes(), $C->get('created'), $C->get('updated')));
            }
            break;

        case 'list':
            $cannedResponses = $this->getQuerySet($options);

            foreach ($cannedResponses as $C) {
                $this->stdout->write(sprintf(
                    "%s %s %s %s %s %s %s %s\n",
                    Dept::getNameById($C->get('dept_id')), $C->isEnabled(), $C->getTitle(), $C->getResponse(), $C->get('lang'),
                    $C->getNotes(), $C->get('created'), $C->get('updated')
                ));
            }
            break;

        default:
            $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }

    function getQuerySet($options, $requireOne=false) {
        $cannedResponses = Canned::objects();

        return $cannedResponses;
    }

    static function create($vars=false) {
        $cannedResponse = new Canned($vars);
        $cannedResponse->save();

        return $cannedResponse;
    }
}
Module::register('canned_response', 'CannedResponseManager');
?>
