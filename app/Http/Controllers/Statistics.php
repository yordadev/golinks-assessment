<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use App\Services\GithubService;
use App\Services\ResponseBuilderService;

class Statistics extends Controller
{
    /**
     * * process(Request $request)
     * 
     * This validates user input, utilizes the input to get the organization information 
     * from github. Creates a collection, pagniates and then returns a 200 responsse.
     * 
     * @param Request $request
     * 
     * @return Response 
     */
    public function process(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'organization' => 'required|string|max:255',
            'limit'        => 'nullable|numeric',
            'page'         => 'nullable|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 404);
        }

        // Initalize Github Service
        // See App/Services/GithubService.php
        $github = new GithubService();

        // Initalize the Response Builder Service
        // See App/Services/ResponseBuilderService.php
        $responseBuilder = new ResponseBuilderService();

        // check cache for organization else fetch and build up the data structure in GithubService
        if (!$github->fetchGithubInformation(strtolower($request->organization))) {
            // organization not found
            $status = 404;
            return response()->json($responseBuilder->build(['errors' => $github->error], $status, [], $request), $status);
        }

        if (!$github->fetchGithubInformation(strtolower($request->organization))) {
            // internal error
            $status = 500;
            return response()->json($responseBuilder->build(['errors' => $github->error], $status, [], $request), $status);
        }

        if (!$github->getContributorCommitHistory(strtolower($request->organization))) {
            // internal error
            $status = 500;
            return response()->json($responseBuilder->build(['errors' => $github->error], $status, [], $request), $status);
        }

        // sort the organization contributors by commit
        $github->sortOrganizationContributorsByCommits();

        // Bonus: 
        // Add pagination to the API endpoint to reduce the size of the response. 
        if (isset($request->limit)) {
            $github->limit = $request->limit;
        }

        // paginate contributors
        $contributors = $github->paginateContributors($request);

        // build the response obj
        $pages = $github->organization['contributors']->count() / $github->limit;

        $payload = $github->organization;
        $payload['contributors'] = $contributors;

        // parepare links information
        if (isset($request->page)) {
            $page = $request->page;
        } else {
            $page = 1;
        }

        // determine next page
        if ($page == $pages) {
            $next_page = null;
        } else {
            $next_page = url('v1/organization?organization=' . $request->organization . '&page=' . ($page + 1));
        }

        // build links
        $links = [
            'next_page' => $next_page,
            'page'      => $page,
            'limit'     => (int) $github->limit,
            'pages'     => round($pages)
        ];

        // set status code
        $status = 200;

        // return response
        return response()->json($responseBuilder->build($payload, $status, $links, $request), $status);
    }
}
