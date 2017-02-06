<?php

class FormEntryValManager extends Module {
    var $prologue = 'CLI Form Entry manager';

    var $arguments = array(
        'action' => array(
            'help' => 'Action to be performed',
            'options' => array(
                'import' => 'Import form entries from YAM: file',
                'export' => 'Export form entries from the system to CSV or YAML',
                'list' => 'List form entries based on search criteria',
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

          if (!$options['file'] || $options['file'] == '-')
              $options['file'] = 'php://stdin';
          if (!($this->stream = fopen($options['file'], 'rb')))
              $this->fail("Unable to open input file [{$options['file']}]");

          //place file into array
          $data = YamlDataParser::load($options['file']);
          $ctr = 0;
          $ctr0 = 0;
          $ctr1 = 0;
          $ctr2 = 0;
          $ctr3 = 0;
          //processing for form entry values
          foreach ($data as $D)
          {
            $form_entry_values = $D['form_entry_values'];
            $ticket_id = Ticket::getIdByNumber($D['ticket_number']);

            foreach ($form_entry_values as $fev)
            {
              $form_id = self::getFormIdByName($fev['form_name']);
              $entry_id = self::getFormEntryByCombo($form_id, $ticket_id);
              $field_id = self::getFieldIdByCombo($form_id, $fev['field_label'], $fev['field_name']);
              $field_type = self::getFieldTypeById($field_id);
              // var_dump('field type is ' . $field_type);

              //if value is a list value, map to the id of the list item
              if(strpos($field_type, 'list') !== false)
              {
                $arr = explode("\"", $fev['value']);
                // var_dump('old is ' . $arr[1]);
                $list_id = self::getListIdByName($arr[3]);
                $arr[1] = $list_id;
                // var_dump('new is ' . $arr[1]);
                $fev['value'] = implode("\"",$arr);
              }

              //if value is for an attachment field, map file id(s)
              if($field_type == 'files' && $fev['value'] != '[]')
              {
                $ctr++;
                // var_dump('val ' . $fev['value'] . ' type ' . $field_type);
                $arr = explode("\"", $fev['value']);

                switch (count($arr))
                {
                  //just a file id(s) in [], sep by commas
                  case 1:
                    $csarr = explode(",", $fev['file_signature']);
                    if(count($csarr) > 1)
                    {
                      foreach ($csarr as $c)
                      {
                        $file_id_mult[] = self::getFileIdBySignature($c);
                        // var_dump('file id is ' . $file_id);
                      }
                      $file_ids_string = implode(',', $file_id_mult);
                      $fev['value'] = '[' . $file_ids_string . ']';
                      // var_dump('val is ' . $fev['value']);
                    }
                    else
                    {
                      $file_id = self::getFileIdBySignature($csarr[0]);
                      // var_dump('file id is ' . $file_id);
                      $fev['value'] = '[' . $file_id . ']';
                      // var_dump('val is ' . $fev['value']);
                    }
                    break;

                  //json format, 1 file
                  case 3:
                    $ctr3++;
                    $arr = explode("\"", $fev['file_signature']);
                    $arr[2] = ltrim($arr[2], ':');
                    $arr[2] = rtrim($arr[2], '}');
                    // var_dump('arr2 is ' . $arr[2]);
                    $file_id = self::getFileIdBySignature($arr[2]);
                    $arr[2] = ':' . $file_id . '}';
                    // var_dump('file id is ' . $file_id);
                    $fev['value'] = implode("\"", $arr);
                    // var_dump('val is ' . $fev['value']);
                    break;

                  //json format, 2 files
                  // {"TPM1532HE_CloneData.zip":460,"Switch on channel CMND.jpg":463}"
                  case 5:
                    $ctr2++;
                    $arr = explode("\"", $fev['file_signature']);
                    //file 1
                    $arr[2] = ltrim($arr[2], ':');
                    $arr[2] = rtrim($arr[2], ',');
                    // var_dump('arr2 is ' . $arr[2]);
                    $file_id1 = self::getFileIdBySignature($arr[2]);
                    // var_dump('file id is ' . $file_id1);
                    $arr[2] = ':' . $file_id1 . ',';
                    // var_dump('file id is ' . $file_id1);

                    //file 2
                    $arr[4] = ltrim($arr[4], ':');
                    $arr[4] = rtrim($arr[4], '}');
                    // var_dump('arr2 is ' . $arr[4]);
                    $file_id2 = self::getFileIdBySignature($arr[4]);
                    // var_dump('file id is ' . $file_id2);
                    $arr[4] = ':' . $file_id2 . '}';
                    $fev['value'] = implode("\"", $arr);
                    // var_dump('val is ' . $fev['value']);
                    break;

                  //json format, 3 files
                  case 7:
                    $ctr1++;
                    $arr = explode("\"", $fev['file_signature']);
                    //file 1
                    $arr[2] = ltrim($arr[2], ':');
                    $arr[2] = rtrim($arr[2], ',');
                    // var_dump('arr2 is ' . $arr[2]);
                    $file_id1 = self::getFileIdBySignature($arr[2]);
                    // var_dump('file id is ' . $file_id1);
                    $arr[2] = ':' . $file_id1 . ',';

                    //file 2
                    $arr[4] = ltrim($arr[4], ':');
                    $arr[4] = rtrim($arr[4], ',');
                    // var_dump('arr2 is ' . $arr[4]);
                    $file_id2 = self::getFileIdBySignature($arr[4]);
                    // var_dump('file id is ' . $file_id2);
                    $arr[4] = ':' . $file_id2 . ',';

                    //file 3
                    $arr[6] = ltrim($arr[6], ':');
                    $arr[6] = rtrim($arr[6], '}');
                    // var_dump('arr2 is ' . $arr[6]);
                    $file_id3 = self::getFileIdBySignature($arr[6]);
                    // var_dump('file id is ' . $file_id3);
                    $arr[6] = ':' . $file_id3 . '}';
                    $fev['value'] = implode("\"", $arr);
                    // var_dump('val is ' . $fev['value']);
                    break;

                  //json format, > 3 files
                  default:
                    // var_dump('val is ' . $fev['value']);
                    $ctr0++;
                    break;
                }

              }

              $form_entry_val_import[] = array('entry_id' => $entry_id,
                'field_id' => $field_id,
                'value' => $fev['value']);
            }

          }
          var_dump('count is ' . $ctr);
          var_dump('1 file is ' . $ctr3);
          var_dump('2file is ' . $ctr2);
          var_dump('3file is ' . $ctr1);
          var_dump('none of the above ' . $ctr0);
          // import form entry values
          $errors = array();
          foreach ($form_entry_val_import as $o) {
              if ('self::create' && is_callable('self::create'))
                  @call_user_func_array('self::create', array($o, &$errors, true));
              // TODO: Add a warning to the success page for errors
              //       found here
              $errors = array();
          }
          break;

        case 'export':
          if ($options['yaml'])
          {
            //get the form entry values
            $form_entry_vals = $this->getQuerySet($options);

            //prepare form entry vals for yaml file
            foreach ($form_entry_vals as $form_entry_val)
            {
              $object_type = self::getTypeById($form_entry_val->entry_id);
              if($object_type == 'T')
              {
                $ticket_id = self::getTicketByFormEntry($form_entry_val->entry_id);
                $ticket_number = self::getNumberById($ticket_id);
                $form_id = self::getFormIdById($form_entry_val->field_id);

                $field_label = self::getFieldLabelById($form_entry_val->field_id);
                $field_name = self::getFieldNameById($form_entry_val->field_id);
                $entry_id = self::getFormEntryByCombo($form_id, $ticket_id);
                $field_id = self::getFieldIdByCombo($form_id, $field_label, $field_name);
                $field_type = self::getFieldTypeById($field_id);

                //form entry id
                $form_entry_vals_clean[] = array('- ticket_number' =>  $ticket_number,'  form_entry_values' => '');

                //form entry values for ticket
                array_push($form_entry_vals_clean, array(
                '    - field_id' => $form_entry_val->field_id, '      field_label' => $field_label,
                '      field_name' => $field_name, '      form_name' => self::getFormNameById($form_id),
                '      value' => $form_entry_val->value
                )
                );

                //if value is for an attachment field, map file id(s)
                if($field_type == 'files' && $form_entry_val->value != '[]')
                {
                  // var_dump('val ' . $fev['value'] . ' type ' . $field_type);
                  $arr = explode("\"", $form_entry_val->value);

                  switch (count($arr))
                  {
                    //just a file id(s) in [], sep by commas
                    // [2164,2167]
                    case 1:
                      $csarr = explode(",", $form_entry_val->value);
                      $file_signature[] = '';
                      //more than 1 file
                      if(count($csarr) > 1)
                      {
                        foreach ($csarr as $c)
                        {
                          if(strpos($c, '[') !== false)
                          {
                            $c = ltrim($c, "[");
                            // var_dump('c is ' . $c);
                          }
                          elseif(strpos($c, ']') !== false)
                          {
                            $c = rtrim($c, "]");
                            // var_dump('c is ' . $c);
                          }
                          $file_id = $c;
                          // var_dump('file id is ' . $file_id);
                          // $file_signature = array(self::getFileSignatureById($file_id) . ',');
                          array_push($file_signature, self::getFileSignatureById($file_id));
                        }
                        $file_signature_clean[] = '';
                        // var_dump($file_signature);
                        for ($i=0; $i < count($file_signature); $i++)
                        {
                          if($file_signature[$i])
                          {
                            array_push($file_signature_clean, $file_signature[$i]);
                            // var_dump($file_signature[$i]);
                          }
                        }
                        // var_dump($file_signature_clean);
                        $file_signature_string = implode(',', $file_signature_clean);
                        array_push($form_entry_vals_clean, array('      file_signature' => ltrim($file_signature_string, ',')));
                        // var_dump($file_signature_string);
                      }
                      //only 1 file
                      else
                      {
                        $csarr[0] = ltrim($csarr[0], "[");
                        $csarr[0] = rtrim($csarr[0], "]");
                        $file_id = $csarr[0];
                        // var_dump('file sig is ' . self::getFileSignatureById($file_id));
                        array_push($form_entry_vals_clean, array('      file_signature' => self::getFileSignatureById($file_id)));
                      }
                      break;

                    //json format, 1 file
                    // {"Screen Shot 03-07-16 at 04.21 PM.JPG":224}
                    case 3:
                      $ssarr = explode("\"", $form_entry_val->value);
                      $file_id = ltrim($ssarr[2], ':');
                      $file_id = rtrim($file_id, '}');
                      // var_dump('file id is ' . $file_id);
                      $file_signature_json = self::getFileSignatureById($file_id);
                      $ssarr[2] = ':' . $file_signature_json . '}';
                      $ssarr = implode("\"",$ssarr);
                      array_push($form_entry_vals_clean, array('      file_signature' => $ssarr));
                      $ctr3++;
                      break;

                    //json format, 2 files
                    // {"TPM1532HE_CloneData.zip":460,"Switch on channel CMND.jpg":463}"
                    case 5:
                      //file1
                      $ssarr = explode("\"", $form_entry_val->value);
                      $file_id1 = ltrim($ssarr[2], ':');
                      $file_id1 = rtrim($file_id1, ',');
                      // var_dump('file id is ' . $file_id1);
                      $file_signature_json1 = self::getFileSignatureById($file_id1);
                      $ssarr[2] = ':' . $file_signature_json1 . ',';

                      //file2
                      $file_id2 = ltrim($ssarr[4], ':');
                      $file_id2 = rtrim($file_id2, '}');
                      // var_dump('file id is ' . $file_id2);
                      $file_signature_json2 = self::getFileSignatureById($file_id2);
                      $ssarr[4] = ':' . $file_signature_json2 . '}';
                      $ssarr = implode("\"",$ssarr);
                      array_push($form_entry_vals_clean, array('      file_signature' => $ssarr));

                      $ctr2++;
                      break;

                    //json format, 3 files
                    // {"CSMDump.zip":3730,"MasterCloneData.zip":3736,"rdapp.xml":3739}"
                    case 7:
                      //file1
                      $ssarr = explode("\"", $form_entry_val->value);
                      $file_id1 = ltrim($ssarr[2], ':');
                      $file_id1 = rtrim($file_id1, ',');
                      // var_dump('file id is ' . $file_id1);
                      $file_signature_json1 = self::getFileSignatureById($file_id1);
                      $ssarr[2] = ':' . $file_signature_json1 . ',';

                      //file2
                      $file_id2 = ltrim($ssarr[4], ':');
                      $file_id2 = rtrim($file_id2, ',');
                      // var_dump('file id is ' . $file_id2);
                      $file_signature_json2 = self::getFileSignatureById($file_id2);
                      $ssarr[4] = ':' . $file_signature_json2 . ',';

                      //file3
                      $file_id3 = ltrim($ssarr[6], ':');
                      $file_id3 = rtrim($file_id3, '}');
                      // var_dump('file id is ' . $file_id3);
                      $file_signature_json3 = self::getFileSignatureById($file_id3);
                      $ssarr[6] = ':' . $file_signature_json3 . '}';
                      $ssarr = implode("\"",$ssarr);
                      array_push($form_entry_vals_clean, array('      file_signature' => $ssarr));
                      $ctr1++;
                      break;

                    //json format, > 3 files
                    // {"VES151HE_nvblock_0.bin":1240,"VES151HE_nvblock_1.bin":1243,"VES151HE_nvblock_2.bin":1246,"VES151HE_nvblock_3.bin":1249,"VES151HE_nvblock_4.bin":1252,"VES151HE_nvblock_5.bin":1255,"VES151HE_nvblock_6.bin":1258,"VES151HE_nvblock_7.bin":1261}
                    default:
                      // var_dump('val is ' . $fev['value']);
                      $ctr0++;
                      break;
                  }

                }
              }
            }
            unset($form_entry_vals);

            //export yaml file
            echo (Spyc::YAMLDump($form_entry_vals_clean, false, 0));

            // if(!file_exists('form_entry_value.yaml'))
            // {
            //   $fh = fopen('form_entry_value.yaml', 'w');
            //   fwrite($fh, (Spyc::YAMLDump($form_entry_vals_clean, false, 0)));
            //   fclose($fh);
            // }
            // unset($form_entry_vals_clean);
            }
            else
            {
              $stream = $options['file'] ?: 'php://stdout';
              if (!($this->stream = fopen($stream, 'c')))
                  $this->fail("Unable to open output file [{$options['file']}]");

              fputcsv($this->stream, array('EntryID', 'FieldID', 'Value', 'ValueId'));
              foreach (DynamicFormEntryAnswer::objects() as $F)
                  fputcsv($this->stream,
                          array((string) $F->entry_id, $F->field_id, $F->value, $F->value_id));
            }


            break;

        case 'list':
            $form_entry = $this->getQuerySet($options);

            foreach ($form_entry as $F) {
                $this->stdout->write(sprintf(
                    "%d %s \n",
                    $F->getId(), $F->form_id
                ));
            }

            break;

        default:
            $this->stderr->write('Unknown action!');
        }
        @fclose($this->stream);
    }


    function getQuerySet($options, $requireOne=false) {
        $form_entry = DynamicFormEntryAnswer::objects();

        return $form_entry;
    }

    static function create_form_entry_val($vars=array())
    {
      $FeVal = new DynamicFormEntryAnswer($vars);

      //if the entry value is for priority, set value_id
      if ($vars['field_id'] == 22)
      {
        $FeVal->value_id = Priority::getIdByName($vars['value']);
      }

      //return the form entry value
      return $FeVal;

    }

    static function create($vars, &$error=false, $fetch=false)
    {
        $FevVal = self::getIdByCombo($vars['entry_id'], $vars['field_id'], $vars['value']);
        //see if form entry val exists
        if ($fetch && ($FevVal != '0'))
        {
          // var_dump('match');
          return DynamicFormEntryAnswer::lookup($FevVal);
        }
        else
        {
          // var_dump('new ' . $vars['entry_id'] . ' ' .  $vars['field_id']);
          $Fev = self::create_form_entry_val($vars);
          $Fev->save();
          return $Fev->entry_id;
        }

    }

    //form entry value (value field)
    private function getIdByCombo($entry_id, $field_id,$value)
    {
      $row = DynamicFormEntryAnswer::objects()
          ->filter(array(
            'entry_id'=>$entry_id,
            'field_id'=>$field_id,
            'value'=>$value))
          ->values_flat('value')
          ->first();

      return $row ? $row[0] : 0;
    }

    //object_type
    static function getTypeById($id) {
        $row = DynamicFormEntry::objects()
            ->filter(array('id'=>$id))
            ->values_flat('object_type')
            ->first();

        return $row ? $row[0] : 0;
    }

    //ticket id
    static function getTicketByFormEntry($id) {
        $row = DynamicFormEntry::objects()
            ->filter(array('id'=>$id))
            ->values_flat('object_id')
            ->first();

        return $row ? $row[0] : 0;
    }

    //Form Entry Id
    static function getFormEntryByCombo($form_id, $object_id) {
        $row = DynamicFormEntry::objects()
            ->filter(array('form_id'=>$form_id, 'object_id'=>$object_id))
            ->values_flat('id')
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

    //field Label
    static function getFieldLabelById($id) {
        $row = DynamicFormField::objects()
            ->filter(array('id'=>$id))
            ->values_flat('label')
            ->first();

        return $row ? $row[0] : 0;
    }

    //field Name
    static function getFieldNameById($id) {
        $row = DynamicFormField::objects()
            ->filter(array('id'=>$id))
            ->values_flat('name')
            ->first();

        return $row ? $row[0] : 0;
    }

    //field Id
    static function getFieldIdByCombo($form_id, $label, $name) {
        $row = DynamicFormField::objects()
            ->filter(array('form_id'=>$form_id, 'label'=>$label, 'name'=>$name))
            ->values_flat('id')
            ->first();

        return $row ? $row[0] : 0;
    }

    //field Type
    static function getFieldTypeById($field_id) {
        $row = DynamicFormField::objects()
            ->filter(array('id'=>$field_id))
            ->values_flat('type')
            ->first();

        return $row ? $row[0] : 0;
    }

    //Form Id By Form Entry
    static function getFormIdById($id) {
        $row = DynamicFormField::objects()
            ->filter(array('id'=>$id))
            ->values_flat('form_id')
            ->first();

        return $row ? $row[0] : 0;
    }

    //Form Name
    static function getFormNameById($id) {
        $row = DynamicForm::objects()
            ->filter(array('id'=>$id))
            ->values_flat('title')
            ->first();

        return $row ? $row[0] : 0;
    }

    //Form Id By Name
    static function getFormIdByName($name) {
        $row = DynamicForm::objects()
            ->filter(array('title'=>$name))
            ->values_flat('id')
            ->first();

        return $row ? $row[0] : 0;
    }

    //List Item ID
    static function getListIdByName($name) {
        $row = DynamicListItem::objects()
            ->filter(array('value'=>$name))
            ->values_flat('id')
            ->first();

        return $row ? $row[0] : 0;
    }

    private function getFileIdBySignature($signature)
    {
      $row = AttachmentFile::objects()
          ->filter(array(
            'signature'=>$signature))
          ->values_flat('id')
          ->first();

      return $row ? $row[0] : 0;
    }
}
Module::register('form_entry_val', 'FormEntryValManager');
?>
