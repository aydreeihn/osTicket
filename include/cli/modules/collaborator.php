<?php
class CollaboratorManager extends Module {
    var $prologue = 'CLI collaborator manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import collaborators from CSV or YAML file',
                'export' => 'Export collaborators from the system to CSV',
                'list' => 'List collaborators based on search criteria',
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

          //create collaborators
          $errors = array();
          foreach ($data as $D) {
              $user_id = User::getIdByName($D['user_name']);
              $ticket_id = Ticket::getIdByNumber($D['ticket_number']);
              $thread_id = self::getThreadIdByCombo($ticket_id, 'T');

              $D['user_id'] = $user_id;
              $D['thread_id'] = $thread_id;
              unset($D['user_name']);
              unset($D['ticket_number']);

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
              $collaborators = $this->getQuerySet($options);

              //format the array nicely
              foreach ($collaborators as $C) {
                $ticketId = self::getObjectByThread($C->get('thread_id'));
                $ticketNumber = self::getNumberById($ticketId);

                $clean[] = array(
                  'isactive' => $C->get('isactive'), 'ticket_number' => $ticketNumber, 'user_name' => User::getNameById($C->get('user_id')),
                  'role' => $C->get('role'), 'created' => $C->get('created'), 'updated' => $C->get('updated'));
              }

              //export yaml file
              // echo (Spyc::YAMLDump($clean));

              if(!file_exists('collaborator.yaml')) {
                $fh = fopen('collaborator.yaml', 'w');
                fwrite($fh, (Spyc::YAMLDump($clean)));
                fclose($fh);
              }
            }
            else {
              $stream = $options['file'] ?: 'php://stdout';
              if (!($this->stream = fopen($stream, 'c')))
                  $this->fail("Unable to open output file [{$options['file']}]");

              fputcsv($this->stream, array('isactive', 'Thread Id', 'User Name', 'Role', 'Created', 'Updated'));
              foreach (Collaborator::objects() as $C)
                  fputcsv($this->stream,
                          array((string) $C->get('isactive'), $C->get('thread_id'), User::getNameById($C->get('user_id')),
                          $C->get('role'), $C->get('created'), $C->get('updated')));
            }
            break;

        case 'list':
            $collaborators = $this->getQuerySet($options);

            foreach ($collaborators as $C) {
                $this->stdout->write(sprintf(
                    "%d %s %s %s %s %s\n",
                    $C->get('isactive'), $C->get('thread_id'), User::getNameById($C->get('user_id')),
                    $C->get('role'), $C->get('created'), $C->get('updated')
                ));
            }
            break;

        default:
            $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }

    function getQuerySet($options, $requireOne=false) {
        $collaborators = Collaborator::objects();

        return $collaborators;
    }

    static function create($vars=false) {
        $collaborator = new Collaborator($vars);
        return $collaborator;
    }

    private function __create($vars, &$error=false, $fetch=false) {
        //see if collaborator exists
        if ($fetch && ($collaboratorId=self::getIdByCombo($vars['user_id'], $vars['thread_id'])))
          return Collaborator::lookup($collaboratorId);
        else {
          $collaborator = self::create($vars);
          $collaborator->save();
          return $collaborator->id;
        }
    }

    private function getIdByCombo($userId, $threadId) {
      $row = Collaborator::objects()
          ->filter(array(
            'user_id'=>$userId,
            'thread_id'=>$threadId))
          ->values_flat('id')
          ->first();

      return $row ? $row[0] : 0;
    }

    //thread object id
    static function getObjectByThread($thread_id) {
        $row = Thread::objects()
            ->filter(array('id'=>$thread_id))
            ->values_flat('object_id')
            ->first();

        return $row ? $row[0] : 0;
    }

    //ticket Number
    static function getNumberById($id) {
        $row = Ticket::objects()
            ->filter(array('ticket_id'=>$id))
            ->values_flat('number')
            ->first();

        return $row ? $row[0] : 0;
    }

    private function getThreadIdByCombo($object_id, $object_type) {
      $row = Thread::objects()
          ->filter(array(
            'object_id'=>$object_id,
            'object_type'=>$object_type))
          ->values_flat('id')
          ->first();

      return $row ? $row[0] : 0;
    }
}
Module::register('collaborator', 'CollaboratorManager');
?>
