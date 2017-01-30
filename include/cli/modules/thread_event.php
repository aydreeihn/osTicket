<?php

class ThreadEventManager extends Module {
    var $prologue = 'CLI thread entry manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import thread events from yaml file',
                'export' => 'Export thread events from the system to CSV or yaml',
                'list' => 'List thread events based on search criteria',
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

          // $tcount = 0;
          // $acount = 0;
          // $ncount = 0;
          // $gcount = 0;
          // $scount = 0;
          // $ucount = 0;

          //processing for thread entries
          foreach ($data as $D)
          {
            //variables to map back to ids
            $ticket_id = Ticket::getIdByNumber($D['ticket_number']);
            $task_id = Task::lookupIdByNumber($D['task_number']);
            $object_type = $D['object_type'];

            if($D['object_type'] == 'T')
            {
              // $tcount++;
              $thread_id = self::getThreadIdByCombo($ticket_id, $object_type);
            }
            elseif($D['object_type'] == 'A')
            {
              // $acount++;
              $thread_id = self::getThreadIdByCombo($task_id, $object_type);
            }
            else
            {
              // $ncount++;
              $thread_id = 0;
            }

            $staff_id = self::getStaffIdByEmail($D['staff']);
            $team_id = self::getTeamIdByName($D['team']);
            $dept_id = self::getDeptIdByName($D['department']);
            $topic_id = self::getTopicIdByName($D['topic']);
            $user_id = self::getUserIdByEmail($D['user_email']);

            //set user id in data string to match new user ids
            if($D['state'] == 'collab')
            {
              $arr = explode("\"", $D['data']);
              $arr[3] = $user_id;
              $D['data'] = implode("\"",$arr);
            }

            //set staff id in data string to match new staff ids
            if($D['state'] == 'assigned')
            {
              // $assigned = explode("\"", $D['data']);
              $assigned = json_decode($D['data'], true);
              if(!is_array($assigned['staff']) && !is_null($assigned['staff']))
              {
                // $ucount++;
                $assigned['staff'] = $staff_id;
                $imp_inner = implode($assigned);
                $D['data'] = '{"staff":' . $imp_inner . '}';
                // var_dump('data is ' . $D['data']);
              }
              elseif($assigned['staff'][0])
              {
                // $scount++;
                $assigned['staff'][0] = $staff_id;
                $imp_inner = implode(',"',$assigned['staff']);
                $D['data'] = '{"staff":[' . $imp_inner . '"]}';
              }
            }

            $thread_event_import[] = array('thread_id' => $thread_id, 'staff_id' => $staff_id,
            'team_id' => $team_id,'dept_id' => $dept_id,
            'topic_id' => $topic_id, 'state' => $D['state'],
            'data' => $D['data'], 'username' => $D['username'],
            'uid' => $D['uid'], 'uid_type' => $D['uid_type'],
            'annulled' => $D['annulled'], 'timestamp' => $D['timestamp']
            );

          }
          // var_dump('tcount is ' .  $tcount . ' acount is ' . $acount . ' ncount is ' . $ncount);
          // var_dump('over 21322 is ' . $gcount);
          // var_dump('assigned to staff array count is ' . $scount . ' assigned to staff !array count is ' . $ucount);

          //create threads with a unique name as a new record
          $errors = array();
          foreach ($thread_event_import as $o) {
              if ('self::__create' && is_callable('self::__create'))
                  @call_user_func_array('self::__create', array($o, &$errors, true));
              // TODO: Add a warning to the success page for errors
              //       found here
              $errors = array();
          }

          break;

        case 'export':
            if ($options['yaml'])
            {
              //get the thread entries
              $thread_events = self::getQuerySet($options);

              //format the array nicely
              foreach ($thread_events as $thread_event)
              {
                //object type
                $object_type = self::getObjectTypeByThread($thread_event->thread_id);

                //object id
                $object_id = self::getObjectIdByThread($thread_event->thread_id);

                if($thread_event->thread_id == 0)
                {
                  $ticket_number = '';
                  $task_number = '';
                  $object_type = 'N';
                }
                //get ticket number
                elseif($object_type == 'T')
                {
                  $ticket_number = self::getNumberById($object_id);
                  $task_number = '';
                }
                //otherwise get task title
                elseif($object_type == 'A')
                {
                  $ticket_number = '';
                  $task_number = self::getTaskById($object_id);
                }

                //staff email
                $staff = self::getStaffEmailById($thread_event->staff_id);

                //team
                $team_name = self::getTeamById($thread_event->team_id);

                //topic name
                $topic_name = self::getTopicById($thread_event->topic_id);

                if($thread_event->state == 'collab')
                {
                  $arr = explode("\"", $thread_event->data);
                  $user_id = $arr[3];
                  $user_email = self::getUserEmailById($user_id);
                }

                $clean[] = array('object_type' => $object_type, 'ticket_number' => $ticket_number,
                'task_number' => $task_number, 'staff' => $staff,
                'team' => $team_name,'department' => $thread_event->getDept(),
                'topic' => $topic_name, 'state' => $thread_event->state,
                'data' => $thread_event->data, 'user_email' => $user_email, 'username' => $thread_event->username,
                'uid' => $thread_event->uid, 'uid_type' => $thread_event->uid_type,
                'annulled' => $thread_event->annulled, 'timestamp' => $thread_event->timestamp
                );

              }


              //export yaml file
              echo Spyc::YAMLDump(array_values($clean), true, false, true);

              // if(!file_exists('thread_event.yaml'))
              // {
              //   $fh = fopen('thread_event.yaml', 'w');
              //   fwrite($fh, (Spyc::YAMLDump($clean)));
              //   fclose($fh);
              // }

            }
            else
            {
              $stream = $options['file'] ?: 'php://stdout';
              if (!($this->stream = fopen($stream, 'c')))
                  $this->fail("Unable to open output file [{$options['file']}]");

              fputcsv($this->stream, array('thread_id', 'staff_id', 'team_id', 'dept_id',
                                           'topic_id', 'state', 'data', 'username',
                                           'uid', 'uid_type', 'annulled', 'timestamp'));
              foreach (ThreadEvent::objects() as $thread_event)
                  fputcsv($this->stream,
                          array((string) $thread_event->thread_id, $thread_event->staff_id,
                                         $thread_event->team_id, $thread_event->dept_id,
                                         $thread_event->topic_id, $thread_event->state,
                                         $thread_event->data, $thread_event->username,
                                         $thread_event->uid, $thread_event->uid_type,
                                         $thread_event->annulled, $thread_event->timestamp,
                                       ));
            }

            break;

        case 'list':
            $thread_events = $this->getQuerySet($options);

            foreach ($thread_events as $thread_event) {
                $this->stdout->write(sprintf(
                    "%d %s <%s> %s\n",
                     $thread_event->thread_id, $thread_event->state,
                     $thread_event->data, $thread_event->timestamp
                ));
            }

            break;

        default:
            $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }


    function getQuerySet($options, $requireOne=false) {
        $thread_events = ThreadEvent::objects();

        return $thread_events;
    }

    //ticket Number
    static function getNumberById($id) {
        $row = Ticket::objects()
            ->filter(array('ticket_id'=>$id))
            ->values_flat('number')
            ->first();

        return $row ? $row[0] : 0;
    }

    static function getTaskById($id) {
        $row = Task::objects()
            ->filter(array('id'=>$id))
            ->values_flat('number')
            ->first();

        return $row ? $row[0] : 0;
    }

    private function getObjectIdByThread($thread_id)
    {
      $row = Thread::objects()
          ->filter(array(
            'id'=>$thread_id))
          ->values_flat('object_id')
          ->first();

      return $row ? $row[0] : 0;
    }

    private function getObjectTypeByThread($thread_id)
    {
      $row = Thread::objects()
          ->filter(array(
            'id'=>$thread_id))
          ->values_flat('object_type')
          ->first();

      return $row ? $row[0] : 0;
    }

    static function getStaffEmailById($id) {
        $list = Staff::objects()->filter(array(
            'staff_id'=>$id,
        ))->values_flat('email')->first();

        if ($list)
            return $list[0];
    }

    static function getStaffIdByEmail($email) {
        $list = Staff::objects()->filter(array(
            'email'=>$email,
        ))->values_flat('staff_id')->first();

        if ($list)
            return $list[0];
    }

    static function getUserEmailById($id) {
        $list = UserEmailModel::objects()->filter(array(
            'user_id'=>$id,
        ))->values_flat('address')->first();

        if ($list)
            return $list[0];
    }

    static function getUserIdByEmail($email) {
        $list = UserEmailModel::objects()->filter(array(
            'address'=>$email,
        ))->values_flat('user_id')->first();

        if ($list)
            return $list[0];
    }

    static function getTeamById($id) {
        $list = Team::objects()->filter(array(
            'team_id'=>$id,
        ))->values_flat('name')->first();

        if ($list)
            return $list[0];
    }

    static function getTeamIdByName($team) {
        $list = Team::objects()->filter(array(
            'name'=>$team,
        ))->values_flat('team_id')->first();

        if ($list)
            return $list[0];
    }

    static function getDeptIdByName($name) {
        $list = Dept::objects()->filter(array(
            'name'=>$name,
        ))->values_flat('id')->first();

        if ($list)
            return $list[0];
    }

    static function getTopicById($id) {
        $list = Topic::objects()->filter(array(
            'topic_id'=>$id,
        ))->values_flat('topic')->first();

        if ($list)
            return $list[0];
    }

    static function getTopicIdByName($topic_name) {
        $list = Topic::objects()->filter(array(
            'topic'=>$topic_name,
        ))->values_flat('topic_id')->first();

        if ($list)
            return $list[0];
    }

    private function getThreadIdByCombo($ticket_id, $object_type)
    {
      $row = Thread::objects()
          ->filter(array(
            'object_id'=>$ticket_id,
            'object_type'=>$object_type))
          ->values_flat('id')
          ->first();

      return $row ? $row[0] : 0;
    }

    private function getIdByCombo($thread_id, $state, $timestamp)
    {
      $row = ThreadEvent::objects()
          ->filter(array(
            'thread_id'=>$thread_id,
            'state'=>$state,
            'timestamp'=>$timestamp))
          ->values_flat('id')
          ->first();

      return $row ? $row[0] : 0;
    }

    static function create_thread_event($vars=array())
    {
      $thread_event = new ThreadEvent($vars);

      //return the thread entry
      return $thread_event;

    }

    static function __create($vars, &$error=false, $fetch=false) {
        // //see if thread entry exists
        // if ($fetch && ($threadEventId=self::getIdByCombo($vars['thread_id'], $vars['state'], $vars['timestamp'])))
        // {
        //   // var_dump('match');
        //   return ThreadEvent::lookup($threadEventId);
        // }
        // else
        // {
          // var_dump('new');
          $thread_event = self::create_thread_event($vars);
          $thread_event->save();

          return $thread_event->id;
        // }

    }


}
Module::register('thread_event', 'ThreadEventManager');
?>
