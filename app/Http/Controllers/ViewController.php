<?php

namespace App\Http\Controllers;

class ViewController extends Controller
{
    /**
     * * landing()
     * 
     * This method renders the landing page view.
     * 
     * @return View
     */
    public function landing()
    {
        return view('landing');
    }

    
    /**
     * * error()
     * 
     * This method is to capture unused routes and 
     * redirect the request to an external source.
     * 
     * 
     * @return Redirect $url
     */
    public function error()
    {
        // honeypot bonus points
        return redirect('https://www.youtube.com/watch?v=j5a0jTc9S10&t=3s');
    }
}
