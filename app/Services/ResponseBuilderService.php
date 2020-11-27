<?php

namespace App\Services;

use Illuminate\Http\Request;

class ResponseBuilderService
{
    protected $response;

    public function __construct()
    {
        $this->response = [
            'status'  => null,
            'payload' => null,
            'links'   => null,
            'meta'    => null
        ];
    }

    
    /**
     * * build($data, $status, $links, Request $request)
     * 
     *  This method is a way to have a unified response across all controllers.
     * 
     * @param Array $data
     * @param Integer $status
     * @param Array $linkss
     * @param Request $request
     * 
     * @return Array $this->response
     */
    public function build($data, $status, $links, Request $request)
    {
        $this->response['status']  = $status;
        $this->response['payload'] = $data;
        $this->response['links']   = $links ?? [];
        $this->response['meta']    = [
            'from' => $request->ip(),
            'agent' => $request->header('User-Agent'),
            'origin' => $request->headers->get('Origin')
        ];
        return $this->response;
    }
}
