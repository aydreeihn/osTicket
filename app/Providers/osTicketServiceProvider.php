<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class osTicketServiceProvider extends ServiceProvider{
   protected $defer = true;

   public function register() {
      $this->app->singleton("osTicket",function($app){

         // here are the contents of the legacy index.php:
         require_once “index.php”;
         $bootstrap = new Bootstrap(
            $app->environment(), $this->createOptions()
         );
var_dump('hit');
         return $bootstrap->init();
      });
   }
}
