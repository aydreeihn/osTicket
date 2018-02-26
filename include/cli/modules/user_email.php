<?php

class UserEmailManager extends Module {
    var $prologue = 'CLI user email manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import user emails from CSV or YAML file',
                'export' => 'Export user emails from the system to CSV',
                'list' => 'List user emails based on search criteria',
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
            //look up user id by name
            $user_id = User::getIdByName($D['user_name']);

            $D['user_id'] = $user_id;
            unset($D['user_name']);

            //create user emails
            if ('self::create' && is_callable('self::create'))
                @call_user_func_array('self::create', array($D, &$errors, true));
          }

            break;

        case 'export':
            if ($options['yaml']) {
              //get the agents
              $emails = $this->getQuerySet($options);

              //format the array nicely
              foreach ($emails as $E) {
                $clean[] = array('user_name' => User::getNameById($E->get('user_id')),
                                 'flags' => $E->get('flags'), 'address' => $E->get('address'));
              }

              //export yaml file
              // echo (Spyc::YAMLDump($clean));

              if(!file_exists('user_email.yaml')) {
                $fh = fopen('user_email.yaml', 'w');
                fwrite($fh, (Spyc::YAMLDump($clean)));
                fclose($fh);
              }
            }
            else {
              $stream = $options['file'] ?: 'php://stdout';
              if (!($this->stream = fopen($stream, 'c')))
                  $this->fail("Unable to open output file [{$options['file']}]");

              fputcsv($this->stream, array('User Name', 'Flags', 'Address'));
              foreach (UserEmail::objects() as $E)
                  fputcsv($this->stream,
                          array((string) User::getNameById($E->get('user_id')), $E->get('flags'), $E->get('address')));
            }

            break;

        case 'list':
            $emails = $this->getQuerySet($options);

            foreach ($emails as $E) {
                $this->stdout->write(sprintf(
                    "%s %d <%s>\n",
                    User::getNameById($E->get('user_id')), $E->get('flags'), $E->get('address')
                ));
            }

            break;

        default:
            $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }

    function getQuerySet($options, $requireOne=false) {
        $emails = UserEmail::objects();

        return $emails;
    }

    static function create($vars=false) {
        $email = new UserEmail($vars);
        $email->save();

        return $email;
    }
}
Module::register('user_email', 'UserEmailManager');
?>
