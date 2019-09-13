<?php
/*********************************************************************
    http.php

    HTTP controller for the osTicket API

    Jared Hancock
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
// Use sessions — it's important for SSO authentication, which uses
// /api/auth/ext
define('DISABLE_SESSION', false);

require 'api.inc.php';

# Include the main api urls
require_once INCLUDE_DIR."class.dispatcher.php";
require_once INCLUDE_DIR."class.ajax.php";

$dispatcher = patterns('',
        url_post("^/tickets\.(?P<format>xml|json|email)$", array('api.tickets.php:TicketApiController','create')),
        url('^/tasks/', patterns('',
                url_post("^cron$", array('api.cron.php:CronApiController', 'execute'))
         )),
         url('^/form/', patterns('',
             url_get('^(?P<id>\d+)/(?P<api>\w+)/fields/view$', array('ajax.forms.php:DynamicFormsAjaxAPI', 'getAllFields'))
         ))
        );

Signal::send('api', $dispatcher);

# Call the respective function
print $dispatcher->resolve($ost->get_path_info());
?>
