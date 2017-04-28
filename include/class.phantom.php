<?php
/*********************************************************************
    class.phantom.php

    Phantom data class

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2017 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
//adriane
class Phantom extends VerySimpleModel {
  static $meta = array(
      'table' => PHANTOM_TABLE,
      'pk' => array('id'),
      'joins' => array(
        'staff' => array(
            'constraint' => array(
              'object_type' => "'S'",
              'object_id' => 'TicketModel.staff_id'),
              'null' => true,
              ),
          // 'task_staff' => array(
          //     'constraint' => array(
          //       'object_type' => "'S'",
          //       'object_id' => 'TaskModel.staff_id'),
          //       'null' => true,
          //       ),
          'team' => array(
              'constraint' => array(
                'object_type' => "'E'",
                'object_id' => 'TicketModel.team_id'),
                'null' => true,
          ),
          // 'task_team' => array(
          //     'constraint' => array(
          //       'object_type' => "'E'",
          //       'object_id' => 'TaskModel.team_id'),
          //       'null' => true,
          // ),
          'dept' => array(
              'constraint' => array(
                'object_type' => "'D'",
                'object_id' => 'TicketModel.dept_id'),
                'null' => true,
          ),
          // 'task_dept' => array(
          //     'constraint' => array(
          //       'object_type' => "'D'",
          //       'object_id' => 'TaskModel.dept_id'),
          //       'null' => true,
          // ),
      )
  );

  function getId() {
      return $this->id;
  }

  static function create($staff, $data, $obj)
  {
    $phantom_log = new static();
    $phantom_log->object_id = $obj->getId();
    $phantom_log->object_type = ObjectModel::getType($obj);
    $phantom_log->staff_id = $staff->getId();
    $phantom_log->data = json_encode($data);
    $phantom_log->created = SqlFunction::NOW();
    $phantom_log->save();
  }

  static function getDeptById($id) {
      $row = self::objects()
          ->filter(array('object_id'=>$id, 'object_type'=>'D'))
          ->values_flat('data')
          ->first();

      if($row)
        $json_data = json_decode($row[0]);

      return $json_data;
  }

  function getPhantomDepts()
  {
    $rows = self::objects()
        ->filter(array('object_type'=>'D'))
        ->distinct('object_id')
        ->values_flat('object_id')
        ->all();

    foreach ($rows as $row)
        $depts[] = $row[0];

    return $depts ? $depts : 0;
  }

  function getPhantomStaff()
  {
    $rows = Phantom::objects()
        ->filter(array('object_type'=>'S'))
        ->distinct('object_id')
        ->values_flat('object_id')
        ->all();

    foreach ($rows as $row)
        $staff[] = $row[0];

    return $staff ? $staff : 0;
  }

  static function getTeamById($id) {
      $row = self::objects()
          ->filter(array('object_id'=>$id, 'object_type'=>'E'))
          ->values_flat('data')
          ->first();

      if($row)
        $json_data = json_decode($row[0]);

      return $json_data;
  }

  static function getStaffById($id) {
      $row = self::objects()
          ->filter(array('object_id'=>$id, 'object_type'=>'S'))
          ->values_flat('data')
          ->first();

      if($row)
        $json_data = json_decode($row[0]);

      return $json_data;
  }

}

class PhantomStaff {

  static $ht = array();

  function __construct(Phantom $pd) {
    $this->ht = json_decode($pd->data);
  }

  function getId() {
    $this->ht['object_id'];
  }

  function getName() {
    return new AgentsName(array('first' => $this->ht[0]->firstname, 'last' => $this->ht[0]->lastname));
  }

  function lookup($obj_id)
  {
    if (!($phantom = Phantom::lookup(array('object_id' => $obj_id, 'object_type' => 'S'))))
      return null;

    return new PhantomStaff($phantom);
  }
}

class PhantomTeam {

  static $ht = array();

  function __construct(Phantom $pd) {
    $this->ht = json_decode($pd->data);
  }

  function getId() {
    $this->ht['object_id'];
  }

  function getName() {
    return $this->ht[0]->name;
  }

  function lookup($obj_id)
  {
    if (!($phantom = Phantom::lookup(array('object_id' => $obj_id, 'object_type' => 'E'))))
      return null;

    return new PhantomTeam($phantom);
  }

  function hasMember()
  {
    return false;
  }
}

class PhantomDept {

  static $ht = array();

  function __construct(Phantom $pd) {
    $this->ht = json_decode($pd->data);
  }

  function getId() {
    $this->ht['object_id'];
  }

  function getName() {
    return $this->ht[0]->name;
  }

  function lookup($obj_id)
  {
    if (!($phantom = Phantom::lookup(array('object_id' => $obj_id, 'object_type' => 'D'))))
      return null;

    return new PhantomDept($phantom);
  }

}
