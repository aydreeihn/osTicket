<?php

class FormFieldManager extends Module {
    var $prologue = 'CLI form field manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import form fields from CSV or YAML file',
                'export' => 'Export form fields from the system to CSV',
                'list' => 'List form fields based on search criteria',
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
            //look up form id by title
            $form_id = DynamicForm::getIdByTitle($D['form_title']);

            $D['form_id'] = $form_id;
            unset($D['form_title']);

            //create form fields
            if ('self::__create' && is_callable('self::__create'))
                    @call_user_func_array('self::__create', array($D, &$errors, true));
                // TODO: Add a warning to the success page for errors
                //       found here
                $errors = array();
          }
            break;

        case 'export':
            if ($options['yaml']) {
              //get the form fields
              $formFields = $this->getQuerySet($options);

              //format the array nicely
              foreach ($formFields as $F) {
                if (!$F->getForm())
                    continue;
                else
                    $clean[] = array(
                      'form_title' => $F->getForm()->getTitle(), 'flags' => $F->get('flags'), 'type' => $F->get('type'),
                      'label' => $F->get('label'), 'name' => $F->get('name'), 'configuration' => $F->get('configuration'),
                      'sort' => $F->get('sort'), 'hint' => $F->get('hint'), 'created' => $F->get('created'),
                      'updated' => $F->get('updated'));
              }

              //export yaml file
              // echo (Spyc::YAMLDump($clean));

              if(!file_exists('form_field.yaml')) {
                $fh = fopen('form_field.yaml', 'w');
                fwrite($fh, (Spyc::YAMLDump($clean)));
                fclose($fh);
              }
            }
            else {
              $stream = $options['file'] ?: 'php://stdout';
              if (!($this->stream = fopen($stream, 'c')))
                  $this->fail("Unable to open output file [{$options['file']}]");

              fputcsv($this->stream, array('Form Title', 'Flags', 'Type', 'Label', 'Name', 'Configuration', 'Sort', 'Hint', 'Created', 'Updated'));
              foreach (DynamicFormField::objects() as $F)
                  fputcsv($this->stream,
                          array((string) $F->getForm()->getTitle(), $F->get('flags'), $F->get('type'), $F->get('label'), $F->get('name'),
                          $F->get('configuration'), $F->get('sort'), $F->get('hint'), $F->get('created'), $F->get('updated')));
            }
            break;

        case 'list':
            $formFields = $this->getQuerySet($options);

            foreach ($formFields as $F) {
                $this->stdout->write(sprintf(
                    "%s %s %s %s %s %s %s %s %s %s\n",
                    $F->getForm()->getTitle(), $F->get('flags'), $F->get('type'), $F->get('label'), $F->get('name'),
                    $F->get('configuration'), $F->get('sort'), $F->get('hint'), $F->get('created'), $F->get('updated')
                ));
            }
            break;

        default:
            $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }

    function getQuerySet($options, $requireOne=false) {
        $formFields = DynamicFormField::objects();

        return $formFields;
    }

    static function create($vars=false) {
        $formField = new DynamicFormField($vars);
        return $formField;
    }

    private function __create($vars, &$error=false, $fetch=false) {
        //see if form field exists
        if ($fetch && ($fieldId=self::getIdByCombo($vars['name'], $vars['label'], $vars['type'], $vars['form_id'])))
          return DynamicFormField::lookup($fieldId);
        else {
          $formField = self::create($vars);
          $formField->save();
          return $formField->id;
        }
    }

    private function getIdByCombo($name, $label, $type, $formId) {
      $row = DynamicFormField::objects()
          ->filter(array(
            'name'=>$name,
            'label'=>$label,
            'type'=>$type,
            'form_id'=>$formId))
          ->values_flat('id')
          ->first();

      return $row ? $row[0] : 0;
    }
}
Module::register('form_field', 'FormFieldManager');
?>
