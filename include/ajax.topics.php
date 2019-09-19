<?php
/*********************************************************************
    ajax.topics.php

    AJAX interface for help topics

    Adriane Alexander <adriane@enhancesoft.com>
    Copyright (c)  2006-2019 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

if(!defined('INCLUDE_DIR')) die('403');

include_once(INCLUDE_DIR.'class.topic.php');
require_once(INCLUDE_DIR.'class.ajax.php');

class TopicsAjaxAPI extends AjaxController {
    function getHelpTopics() {
        return json_encode(Topic::getHelpTopics(false, false, true));
    }
}
