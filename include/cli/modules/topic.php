<?php
class TopicManager extends Module {
    var $prologue = 'CLI help topic manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import help topics from yaml file',
                'export' => 'Export help topics from the system to CSV or yaml',
                'list' => 'List help topics based on search criteria',
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

          foreach ($data as $D)
          {
            $dept_id = self::getDeptIdByName($D['dept_name']);
            $staff_id = self::getStaffIdByEmail($D['staff']);
            $team_id = self::getTeamIdByName($D['team']);
            $sla_id = self::getSLAIdByName($D['sla']);

            $topic_import[] = array('isactive' => $D['isactive'],
            'ispublic' => $D['isactive'], 'dept_id' => $dept_id, 'priority_id' => $D['priority_id'],
            'staff_id' => $staff_id, 'team_id' => $team_id,
            'sla_id' => $sla_id, 'topic' => $D['topic'],
            'notes' => $D['notes'], 'created' => $D['created'], 'updated' => $D['updated']);
          }

          //create topics with a unique name as a new record
          $errors = array();
          foreach ($topic_import as $o) {
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
              //get the topics
              $topics = self::getQuerySet($options);

              //format the array nicely
              foreach ($topics as $topic)
              {
                $clean[] = array('isactive' => $topic->isactive,
                'ispublic' => $topic->ispublic, 'dept_name' => self::getDeptById($topic->dept_id), 'priority_id' => $topic->getPriorityId(),
                'staff' => self::getStaffEmailById($topic->staff_id), 'team' => self::getTeamById($topic->team_id),
                'sla' => self::getSLAById($topic->sla_id), 'topic' => $topic->topic,
                'notes' => $topic->notes, 'created' => $topic->created, 'updated' => $topic->updated);

              }

              //export yaml file
              // echo Spyc::YAMLDump(array_values($clean), true, false, true);

              if(!file_exists('topic.yaml'))
              {
                $fh = fopen('topic.yaml', 'w');
                fwrite($fh, (Spyc::YAMLDump($clean)));
                fclose($fh);
              }

            }
            else
            {
              $stream = $options['file'] ?: 'php://stdout';
              if (!($this->stream = fopen($stream, 'c')))
                  $this->fail("Unable to open output file [{$options['file']}]");

              fputcsv($this->stream, array('Topici Id', 'isactive', 'ispublic', 'Priority Id', 'Department Id', 'Topic', 'Notes'));
              foreach (Topic::objects() as $topic)
                  fputcsv($this->stream,
                          array((string) $topic->getId(), boolval($topic->isactive), boolval($topic->ispublic),
                          $topic->getDeptId(), $topic->getPriorityId(), $topic->topic, $topic->notes));
            }

            break;

        case 'list':
            $topics = $this->getQuerySet($options);

            foreach ($topics as $topic)
            {
                $this->stdout->write(sprintf(
                    "%d %s <%s> %s %s %s %s\n",
                    $topic->getId(), boolval($topic->isactive), boolval($topic->ispublic),
                    $topic->getDeptId(), $topic->getPriorityId(), $topic->topic, $topic->notes)
                );
            }

            break;

        default:
            $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }


    function getQuerySet($options, $requireOne=false) {
        $departments = Topic::objects();

        return $departments;
    }

    static function create($vars=array()) {
        $topic = new Topic($vars);
        return $topic;
    }

    static function __create($vars, &$errors, $fetch=false)
    {
        //see if topic exists
        if ($fetch && ($topicId=Topic::getIdByName($vars['topic'])))
        {
          // var_dump('found match ' . $vars['topic']);
          return Topic::lookup($topicId);
        }
        else
        {
          // var_dump('new ' . $vars['topic']);
          $topic = self::create($vars);
          if (!isset($vars['dept_id']))
              $vars['dept_id'] = 0;
          $vars['id'] = $vars['topic_id'];
          $topic->update($vars, $errors);
          return $topic;
        }
    }

    static function getDeptById($id) {
         $row = Dept::objects()
              ->filter(array('id'=>$id))
              ->values_flat('name')
              ->first();

         return $row ? $row[0] : null;
     }

     static function getDeptIdByName($name) {
         $list = Dept::objects()->filter(array(
             'name'=>$name,
         ))->values_flat('id')->first();

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

     static function getStaffEmailById($id) {
         $list = Staff::objects()->filter(array(
             'staff_id'=>$id,
         ))->values_flat('email')->first();

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

     static function getSLAById($id) {
         $list = SLA::objects()->filter(array(
             'id'=>$id,
         ))->values_flat('name')->first();

         if ($list)
             return $list[0];
     }

     static function getSLAIdByName($team) {
         $list = SLA::objects()->filter(array(
             'name'=>$team,
         ))->values_flat('id')->first();

         if ($list)
             return $list[0];
     }


}
Module::register('topic', 'TopicManager');
?>
