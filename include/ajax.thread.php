<?php
/*********************************************************************
    ajax.thread.php

    AJAX interface for thread

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2015 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

if(!defined('INCLUDE_DIR')) die('403');

include_once(INCLUDE_DIR.'class.ticket.php');
require_once(INCLUDE_DIR.'class.ajax.php');
require_once(INCLUDE_DIR.'class.note.php');
include_once INCLUDE_DIR . 'class.thread_actions.php';

class ThreadAjaxAPI extends AjaxController {

    function lookup() {
        global $thisstaff;

        if(!is_numeric($_REQUEST['q']))
            return self::lookupByEmail();


        $limit = isset($_REQUEST['limit']) ? (int) $_REQUEST['limit']:25;
        $tickets=array();

        $visibility = Q::any(array(
            'staff_id' => $thisstaff->getId(),
            'team_id__in' => $thisstaff->teams->values_flat('team_id'),
        ));
        if (!$thisstaff->showAssignedOnly() && ($depts=$thisstaff->getDepts())) {
            $visibility->add(array('dept_id__in' => $depts));
        }


        $hits = TicketModel::objects()
            ->filter(Q::any(array(
                'number__startswith' => $_REQUEST['q'],
            )))
            ->filter($visibility)
            ->values('number', 'user__emails__address')
            ->annotate(array('tickets' => SqlAggregate::COUNT('ticket_id')))
            ->order_by('-created')
            ->limit($limit);

        foreach ($hits as $T) {
            $tickets[] = array('id'=>$T['number'], 'value'=>$T['number'],
                'info'=>"{$T['number']} — {$T['user__emails__address']}",
                'matches'=>$_REQUEST['q']);
        }
        if (!$tickets)
            return self::lookupByEmail();

        return $this->json_encode($tickets);
    }


    function addRemoteCollaborator($tid, $bk, $id) {
        global $thisstaff;

        if (!($thread=Thread::lookup($tid))
                || !($object=$thread->getObject())
                || !$object->checkStaffPerm($thisstaff))
            Http::response(404, 'No such thread');
        elseif (!$bk || !$id)
            Http::response(422, 'Backend and user id required');
        elseif (!($backend = StaffAuthenticationBackend::getBackend($bk)))
            Http::response(404, 'User not found');

        $user_info = $backend->lookup($id);
        $form = UserForm::getUserForm()->getForm($user_info);
        $info = array();
        if (!$user_info)
            $info['error'] = __('Unable to find user in directory');
        var_dump('hit3');
        return self::_addcollaborator($thread, null, $form, $info);
    }

    //Collaborators utils
    //adriane
    //pass another thing here like cc/bcc t/f
    // function addCollaborator($tid, $uid=0, $cc=true) {
    function addCollaborator($tid, $uid=0, $cc) {
    // function addCollaborator($tid, $uid=array(), $cc) {
        global $thisstaff;

        // var_dump('made it');
        // var_dump('tid is' , $tid);
        // var_dump('$uid is' , $uid);
        // var_dump('$cc is' , $cc);

        if (!($thread=Thread::lookup($tid))
                || !($object=$thread->getObject())
                || !$object->checkStaffPerm($thisstaff))
            Http::response(404, __('No such thread'));

        $collaborators = $thread->getCollaborators();
        $cuids = array();
        $cc_cids = array();
        $bcc_cids = array();
        foreach ($collaborators as $c) {
             $cuids[] = $c->user_id;
             if($c->flags & Collaborator::FLAG_CC)
                $cc_cids[] = $c->user_id;
              else {
                $bcc_cids[] = $c->user_id;
              }
        }
        // var_dump('uid is ' , $uid);
        $user = $uid? User::lookup($uid) : null;
        // var_dump('user is' , $user);

        if(!$_POST)
          var_dump('not a post');

        //If not a post then assume new collaborator form
        // if(!$_POST)
        //   return self::_addcollaborator($thread, $user, null, array(), $cc);

        // var_dump('cuids is ' , $cuids , ' post is ' , $_POST['ccs']);
        // $user = $form = null;
        $users = array();
        if (isset($_POST['id']) && $_POST['id']) //Existing user/
            $user =  User::lookup($_POST['id']);


        if (isset($_POST['ccs']) && $_POST['ccs']) { //multiple users
          $vars = array();
          $del = array();
          if($cc == 'true') {
            $vars['cids'] = $cc_cids;
            foreach ($cc_cids as $cid) {
              if(!in_array(strval($cid), $_POST['ccs']))
              {
                var_dump('trying to delete ccs');
                $errors = $info = array();
                $del[] = strval(Collaborator::getIdByUserId($cid));
              }
            }
          }
          if($cc == 'false') {
            $vars['cids'] = $bcc_cids;
            foreach ($bcc_cids as $cid) {
              if(!in_array(strval($cid), $_POST['ccs']))
              {
                var_dump('trying to delete bccs');
                $errors = $info = array();
                $del[] = strval(Collaborator::getIdByUserId($cid));
              }
            }
          }

          if ($del) {
            $vars['del'] = $del;
            $thread->updateCollaborators($vars, $errors);
          }

          foreach ($_POST['ccs'] as $uid) {
            $users[] =  User::lookup($uid);
          }
        }

        $errors = $info = array();
        if ($user) {
          var_dump('im here. uid is ', $uid);
            // if (($c=$thread->addCollaborator($user,array('isactive'=>1), $errors))) { //works on view
            if (($_POST) && ($c=$thread->addCollaborator($user,array('isactive'=>1), $errors))) {
            // if (($uid || $_POST) && ($c=$thread->addCollaborator($user,array('isactive'=>1), $errors))) {
                var_dump('$_POST is' , $_POST);
                if($cc == 'true') {
                  $c->setFlag(Collaborator::FLAG_ACTIVE, true); //adriane
                  $c->setFlag(Collaborator::FLAG_CC, true);
                  $c->save();
                }
                else {
                  $c->setFlag(Collaborator::FLAG_ACTIVE, true); //adriane
                  $c->setFlag(Collaborator::FLAG_CC, false);
                  $c->save();
                }

                $info = array('msg' => sprintf(__('%s added as a collaborator'),
                            Format::htmlchars($c->getName())));
                return self::_collaborators($thread, $info);
            }
        }

        if ($users) {
          foreach ($users as $u) {
            if (!in_array($u->getId(), $cuids) && ($c2=$thread->addCollaborator($u,
                            array('isactive'=>1), $errors))) {
                if($cc == 'true') {
                  $c2->setFlag(Collaborator::FLAG_ACTIVE, true); //adriane
                  $c2->setFlag(Collaborator::FLAG_CC, true);
                  $c2->save();
                }
                else {
                  $c2->setFlag(Collaborator::FLAG_ACTIVE, true); //adriane
                  $c2->setFlag(Collaborator::FLAG_CC, false);
                  $c2->save();
                }

                $info = array('msg' => sprintf(__('Collaborator(s) added')));
                self::_collaborators($thread, $info);
            }
          }
          if(!$info)
            $info = array('msg' => sprintf(__('Collaborator(s) already exist')));

          return self::_collaborators($thread, $info);
        }

        if($errors && $errors['err']) {
            $info +=array('error' => $errors['err']);
        } else {
            // $info +=array('error' =>__('Unable to add collaborator.').' '.__('Internal error occurred'));
        }

        return self::_addcollaborator($thread, $user, $form, $info, $cc);
    }

    function updateCollaborator($tid, $cid) {
        global $thisstaff;

        if (!($thread=Thread::lookup($tid))
                || !($object=$thread->getObject())
                || !$object->checkStaffPerm($thisstaff))
            Http::response(405, 'No such thread');


        if (!($c=Collaborator::lookup(array(
                            'id' => $cid,
                            'thread_id' => $thread->getId())))
                || !($user=$c->getUser()))
            Http::response(406, 'Unknown collaborator');

        $errors = array();
        if(!$user->updateInfo($_POST, $errors))
            return self::_collaborator($c ,$user->getForms($_POST), $errors);

        $info = array('msg' => sprintf('%s updated successfully',
                    Format::htmlchars($c->getName())));

        return self::_collaborators($thread, $info);
    }

    function viewCollaborator($tid, $cid) {
        global $thisstaff;

        if (!($thread=Thread::lookup($tid))
                || !($object=$thread->getObject())
                || !$object->checkStaffPerm($thisstaff))
            Http::response(404, 'No such thread');


        if (!($collaborator=Collaborator::lookup(array(
                            'id' => $cid,
                            'thread_id' => $thread->getId()))))
            Http::response(404, 'Unknown collaborator');

        return self::_collaborator($collaborator);
    }

    function showCollaborators($tid) {
        global $thisstaff;

        if(!($thread=Thread::lookup($tid))
                || !($object=$thread->getObject())
                || !$object->checkStaffPerm($thisstaff))
            Http::response(404, 'No such thread');

        if ($thread->getCollaborators())
            return self::_collaborators($thread);

        return self::_addcollaborator($thread);
    }

    function previewCollaborators($tid) {
        global $thisstaff;

        if (!($thread=Thread::lookup($tid))
                || !($object=$thread->getObject())
                || !$object->checkStaffPerm($thisstaff))
            Http::response(404, 'No such thread');

        ob_start();
        include STAFFINC_DIR . 'templates/collaborators-preview.tmpl.php';
        // include CLIENTINC_DIR . 'templates/collaborators-preview.tmpl.php';
        $resp = ob_get_contents();
        ob_end_clean();

        return $resp;
    }

    //adriane
    function _addcollaborator($thread, $user=null, $form=null, $info=array(), $cc=null) {
        global $thisstaff;

        $info = array('title' => __('Add a collaborator'));
        ob_start();
        include STAFFINC_DIR . 'templates/user-lookup.tmpl.php';
        $resp = ob_get_contents();
        ob_end_clean();

        return $resp;
    }

    function updateCollaborators($tid) {
        global $thisstaff;

        if (!($thread=Thread::lookup($tid))
                || !($object=$thread->getObject())
                || !$object->checkStaffPerm($thisstaff))
            Http::response(404, 'No such thread');

        $errors = $info = array();
        if ($thread->updateCollaborators($_POST, $errors))
            Http::response(201, $this->json_encode(array(
                            'id' => $thread->getId(),
                            'text' => sprintf('Recipients (%d of %d)',
                                $thread->getNumActiveCollaborators(),
                                $thread->getNumCollaborators())
                            )
                        ));

        if($errors && $errors['err'])
            $info +=array('error' => $errors['err']);

        return self::_collaborators($thread, $info);
    }



    function _collaborator($collaborator, $form=null, $info=array()) {
        global $thisstaff;

        $info += array('action' => sprintf('#thread/%d/collaborators/%d',
                    $collaborator->thread_id, $collaborator->getId()));

        $user = $collaborator->getUser();

        ob_start();
        include(STAFFINC_DIR . 'templates/user.tmpl.php');
        $resp = ob_get_contents();
        ob_end_clean();

        return $resp;
    }

    function _collaborators($thread, $info=array()) {

        ob_start();
        include(STAFFINC_DIR . 'templates/collaborators.tmpl.php');
        $resp = ob_get_contents();
        ob_end_clean();

        return $resp;
    }

    function triggerThreadAction($ticket_id, $thread_id, $action) {
        $thread = ThreadEntry::lookup($thread_id);
        if (!$thread)
            Http::response(404, 'No such ticket thread entry');
        if ($thread->getThread()->getObjectId() != $ticket_id)
            Http::response(404, 'No such ticket thread entry');

        $valid = false;
        foreach ($thread->getActions() as $group=>$list) {
            foreach ($list as $name=>$A) {
                if ($A->getId() == $action) {
                    $valid = true; break;
                }
            }
        }
        if (!$valid)
            Http::response(400, 'Not a valid action for this thread');

        $thread->triggerAction($action);
    }
}
?>
