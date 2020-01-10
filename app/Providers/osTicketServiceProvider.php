<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class osTicketServiceProvider extends ServiceProvider{
   protected $defer = true;

   public function register() {
      $this->app->singleton("osTicket",function($app){

         // Define path to application directory
         defined('APPLICATION_PATH')
             || define('APPLICATION_PATH', realpath( __DIR__ . '/../../application'));
         $bootstrap = new Bootstrap(
            $app->environment(), $this->createOptions()
         );

         return $bootstrap->init();
      });
   }
}
