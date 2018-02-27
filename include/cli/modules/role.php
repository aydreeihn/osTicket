<?php

class RoleManager extends Module {
    var $prologue = 'CLI role manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import roles from yaml file',
                'export' => 'Export roles from the system to CSV or yaml',
                'list' => 'List roles based on search criteria',
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
            'action'=>'store_true', 'help'=>'Export in yaml format'),
        );

    var $stream;

    function run($args, $options) {

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

          //create roles with a unique name as a new record
           $errors = array();
            foreach ($data as $o) {
                if ('self::__create' && is_callable('self::__create'))
                    @call_user_func_array('self::__create', array($o, &$errors, true));
                // TODO: Add a warning to the success page for errors
                //       found here
                $errors = array();
            }
        break;

        case 'export':
            if ($options['yaml']) {
              //get the roles
              $roles = self::getQuerySet($options);

              //format the array nicely
              foreach ($roles as $R) {
                $clean[] = array(
                  'flags' => $R->flags, 'name' => $R->getName(), 'notes' => $R->notes,
                  'permissions' => $R->permissions, 'created' => $R->created, 'updated' => $R->updated);
              }

              //export yaml file
              // echo (Spyc::YAMLDump($clean));

              if(!file_exists('role.yaml')) {
                //create export file
                $fh = fopen('role.yaml', 'w');
                fwrite($fh, (Spyc::YAMLDump($clean)));
                fclose($fh);

                //move file to exports folder
                //rename('/var/www/html/osticket.1.10/role.yaml', '/var/www/html/osticket.1.10/exports/role.yaml');
              }
              // $path = glob('role.yaml');
            }
            else {
              $stream = $options['file'] ?: 'php://stdout';
              if (!($this->stream = fopen($stream, 'c')))
                  $this->fail("Unable to open output file [{$options['file']}]");

              fputcsv($this->stream, array('Flags', 'Name', 'Notes', 'Permissions'));
              foreach (RoleModel::objects() as $role)
                  fputcsv($this->stream,
                          array((string) $role->flags, $role->getName(), $role->notes, $R->permissions));
            }
            break;

        case 'list':
            $roles = $this->getQuerySet($options);

            foreach ($roles as $R) {
                $this->stdout->write(sprintf(
                    "%d %s <%s>%s\n",
                    $R->flags, $R->getName(), $R->notes, $R->permissions
                ));
            }
            break;

        default:
            $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }

    function getQuerySet($options, $requireOne=false) {
        $roles = RoleModel::objects();

        return $roles;
    }

    static function create($vars=false) {
        $role = new Role($vars);
        return $role;
    }

    static function __create($vars, &$errors, $fetch=false) {
        //see if role already exists
        if ($fetch && ($roleId=Role::getIdByName($vars['name'])))
          return $roleId;
        else {
          $role = self::create($vars);
          if ($vars['permissions'])
              $role->updatePerms($vars['permissions']);

          $role->save();
          return $role;
        }
    }
}
Module::register('role', 'RoleManager');
?>
