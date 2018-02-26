<?php

class UserAccountManager extends Module {
    var $prologue = 'CLI user account manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import user accounts from CSV or YAML file',
                'export' => 'Export user accounts from the system to CSV',
                'list' => 'List user accounts based on search criteria',
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

          //create user accounts
          $errors = array();
          foreach ($data as $o) {
              if ('self::create' && is_callable('self::create'))
                  @call_user_func_array('self::create', array($o, &$errors, true));
              else
                echo 'something went wrong';
              // TODO: Add a warning to the success page for errors
              //       found here
              $errors = array();
          }

            break;

        case 'export':
            if ($options['yaml']) {
              //get the agents
              $userAccounts = $this->getQuerySet($options);

              //format the array nicely
              foreach ($userAccounts as $U) {
                $clean[] = array('user_id' => $U->getUserId(), 'status' => $U->getStatus(), 'timezone' => $U->getTimezone(), 'lang' => $U->getLanguage(),
                                 'username' => $U->get('username'),'passwd' => $U->get('passwd'), 'backend' => $U->get('backend'),
                                 'extra' => $U->get('extra'), 'registered' => $U->get('registered'));
              }

              //export yaml file
              // echo (Spyc::YAMLDump($clean));

              if(!file_exists('user_account.yaml')) {
                $fh = fopen('user_account.yaml', 'w');
                fwrite($fh, (Spyc::YAMLDump($clean)));
                fclose($fh);
              }
            }
            else {
              $stream = $options['file'] ?: 'php://stdout';
              if (!($this->stream = fopen($stream, 'c')))
                  $this->fail("Unable to open output file [{$options['file']}]");

              fputcsv($this->stream, array('UserId', 'Status', 'Timezone', 'Lang', 'Username', 'Password', 'Backend', 'Extra', 'Registered'));
              foreach (UserAccount::objects() as $U)
                  fputcsv($this->stream,
                          array((string) $U->getUserId(), $U->getStatus(), $U->getTimezone(), $U->getLanguage(), $U->get('username'),
                                         $U->get('passwd'), $U->get('backend'), $U->get('extra'), $U->get('registered')));
            }

            break;

        case 'list':
            $userAccounts = $this->getQuerySet($options);

            foreach ($userAccounts as $U) {
                $this->stdout->write(sprintf(
                    "%d %d %s %s %s %s %s %s %s\n",
                    $U->getUserId(), $U->getStatus(), $U->getTimezone(), $U->getLanguage(), $U->get('username'),
                    $U->get('passwd'), $U->get('backend'), $U->get('extra'), $U->get('registered')
                ));
            }

            break;

        default:
            $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }

    function getQuerySet($options, $requireOne=false) {
        $userAccounts = UserAccount::objects();

        return $userAccounts;
    }

    static function create($vars=false) {
        $userAccount = new UserAccount($vars);
        $userAccount->save();

        return $userAccount;
    }
}
Module::register('user_account', 'UserAccountManager');
?>
