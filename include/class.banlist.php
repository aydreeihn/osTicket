<?php
/*********************************************************************
    class.banlist.php

    Banned email addresses handle.

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

require_once "class.filter.php";
class Banlist {

    function add($email,$submitter='') {
        return self::getSystemBanList()->addRule('email','equal',$email);
    }

    function remove($email) {
        return self::getSystemBanList()->removeRule('email','equal',$email);
    }

    /**
     * Quick function to determine if the received email-address is in the
     * banlist. Returns the filter of the filter that has the address
     * blacklisted and FALSE if the email is not blacklisted.
     *
     */
    static function isBanned($addr) {

        if (!($filter=self::getFilter()))
            return false;

        $sql='SELECT filter.id '
            .' FROM '.FILTER_TABLE.' filter'
            .' INNER JOIN '.FILTER_RULE_TABLE.' rule'
            .'  ON (filter.id=rule.filter_id)'
            .' WHERE filter.id='.db_input($filter->getId())
            .'   AND filter.isactive'
            .'   AND rule.isactive '
            .'   AND rule.what="email"'
            .'   AND rule.val='.db_input($addr);

        if (($res=db_query($sql)) && db_num_rows($res))
            return $filter;

        return false;
    }

    function includes($email) {
        return self::getSystemBanList()->containsRule('email','equal',$email);
    }

    function ensureSystemBanList() {

        if (!($id=Filter::getByName('SYSTEM BAN LIST')))
            $id=self::createSystemBanList();

        return $id;
    }

    function createSystemBanList() {
        # XXX: Filter::create should return the ID!!!
        $errors=array();
        return Filter::create(array(
            'execorder'     => 99,
            'name'          => 'SYSTEM BAN LIST',
            'isactive'      => 1,
            'match_all_rules' => false,
            'actions'       => array(
                'Nreject',
            ),
            'rules'         => array(),
            'notes'         => __('Internal list for email banning. Do not remove')
        ), $errors);
    }

    function getSystemBanList() {
        return self::ensureSystemBanList();
    }

    static function getFilter() {
        return self::getSystemBanList();
    }
}

require_once "class.list.php";
require_once "class.whitelist.php";
class IPBanlist {

    function add($ipaddress) {
        $errors = array();
        $vars = array('sort'  => 1,
            'value' => $ipaddress,
            'extra' => '');
        $item = self::getIPBanList()->addItem($vars, $errors);

        return $item->save();
    }

    //When an IP is removed from the banlist, add it to the whitelist
    function remove($ipaddress) {
        $list = self::getIPBanList();
        $listItem = $list->getItem($ipaddress);
        $whitelist = IPWhitelist::getIPWhitelist();
        $listItem->set('list_id', $whitelist->id);

        return $listItem->save();
    }

    function isItemUnique($data, &$errors) {
        //see if item is in IP Whitelist
        $whitelist = IPWhitelist::getIPWhitelist();
        if (($whitelist->getItem($data['value']))) {
            $errors['error'] = __('Value already in use in banlist');
            return false;
        }

        try {
            $list=self::getIPBanList();
            $list->getItems()->filter(array('value'=>$data['value']))->one();
            return false;
        }
        catch (DoesNotExist $e) {
            return true;
        }
    }

    function isItemValid($data, &$errors) {
        if (!$data['value'] || !Validator::is_ip($data['value'])) {
            $errors['error'] = __('Valid IP is required');

            return false;
        }
        return true;
    }

    function ensureIPBanList() {
        if (!($list=DynamicList::getByType('ip-banlist'))) {
            $list=self::createIPBanList();
            $list->save();
        }

        return $list;
    }

    function createIPBanList() {
        # XXX: DynamicList::create should return the ID!!!
        $errors=array();
        return DynamicList::create(array(
            'name'          => 'IP Banlist',
            'name_plural'   => 'Banned IPs',
            'sort_mode'     => 'SortCol',
            'masks'         => 13,
            'type'          => 'ip-banlist',
            'notes'         => __('Banned IPs')
        ), $errors);
    }

    function getIPBanList() {
        return self::ensureIPBanList();
    }
}
