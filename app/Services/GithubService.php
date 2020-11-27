<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class GithubService
{
    public function __construct()
    {
        // construct a contributor
        $this->contributor = [
            'username' => null,
            'avatar'   => null,
            'email'    => null,
            'commits'  => 0
        ];

        // construct an organization
        $this->organization = [
            'name' => null,
            'followers' => 0,
            'following' => 0,
            'repositories' => 0,
            'contributors' => collect([])
        ];

        // initiate and null out
        $this->error = null;

        // initate header for github.
        $this->headers = [
            'Authorization' => 'Bearer ' . env('GITHUB_TOKEN')
        ];

        // default limit to 5
        $this->limit = 5;
    }


    /**
     * * fetchGithubInformation($organization)
     * 
     * This method checks the Cache for the specific organization obj.
     * If not present, get from github and place into cache.
     * Set construct organization with dataset.
     * 
     * @param String $organization
     * 
     * @return Boolean
     */
    public function fetchGithubInformation($organization)
    {
        try {
            $this->organization = Cache::remember('organization:' . $organization, 3600, function () use ($organization) {
                $githubResponse = Http::withHeaders($this->headers)->get('https://api.github.com/orgs/' . $organization);
                if ($githubResponse->status() !== 200) {
                    abort($githubResponse->status(), 'Unknown Organization');
                }

                $githubResponse = $githubResponse->json();

                $this->organization['name'] = $githubResponse['name'];
                $this->organization['followers'] = $githubResponse['followers'];
                $this->organization['following'] = $githubResponse['following'];
                $this->organization['repositories'] = $githubResponse['public_repos'];

                return $this->organization;
            });

            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }


    /**
     * * getContributorCommitHistory($organization, $page = 1, $repoCnt = 0)v
     * 
     *  This method requests the organization repositories that are public.
     *  To process the data, a foreach is done through the repositories 
     *  passing the repository name and organization to an additional method
     *  to collect the contributor information for that repository.
     * 
     * @param String $organization
     * @param Integer $page,
     * @param Integer $repoCnt
     * 
     * @return Boolean
     */
    public function getContributorCommitHistory($organization)
    {
        try {
            $orgRepositoryResponse = Http::withHeaders($this->headers)->get('https://api.github.com/orgs/' . $organization . '/repos?type=public');

            if ($orgRepositoryResponse->status() !== 200) {

                abort($orgRepositoryResponse->status(), $orgRepositoryResponse->body());
            }

            $orgRepositoryResponse = $orgRepositoryResponse->json();

            foreach ($orgRepositoryResponse as $repository) {
                if (!$this->collectContributorInformationFrom($organization, $repository['name'])) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }


    /**
     * * collectContributorInformationFrom($organization, $repository)
     * 
     *  This method gets the contributor information from a repository belonging to an organization.
     *  It foreachs through and build a contributor array and pushs it into the organization contributor list.
     * 
     * @param String $organization
     * @param String $repository
     * 
     * @return Boolean
     */
    public function collectContributorInformationFrom($organization, $repository)
    {
        try {

            $repositoryContributorResponse = Http::withHeaders($this->headers)->get('https://api.github.com/repos/' .  $organization . '/' . $repository . '/contributors');


            if ($repositoryContributorResponse->status() !== 200) {

                abort($repositoryContributorResponse->status(), $repositoryContributorResponse->body());
            }

            $repositoryContributorResponse = $repositoryContributorResponse->json();

            foreach ($repositoryContributorResponse as $repoContributor) {
                $contributor = $this->contributor;

                $contributor['username'] = $repoContributor['login'];
                $contributor['avatar']   = $repoContributor['avatar_url'];
                $contributor['email']    = $this->collectUserEmail($repoContributor['login']) ?? 'Unknown Email';
                $contributor['commits']  = $repoContributor['contributions'];

                if ($this->organization['contributors']->contains('username', $contributor['username'])) {
                    foreach ($this->organization['contributors'] as $trackedContributor) {
                        if ($trackedContributor['username'] === $contributor['username']) {
                            $trackedContributor['commits'] += $contributor['commits'];
                            break;
                        }
                    }
                } else {
                    $this->organization['contributors']->push($contributor);
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }


    /**
     * * collectUserEmail($user)
     * 
     *  This method checks to see if the github user has a public email address available 
     *  and returns it else it returns null.
     * 
     * @param String $user
     * 
     * @return String
     */
    public function collectUserEmail($user)
    {
        try {

            $githubUser = Http::withHeaders($this->headers)->get('https://api.github.com/users/' . $user)->json();


            if (!isset($githubUser['email'])) {
                // do nothing
                // email not found
                // place 'Unknown Email' instead since null
                return null;
            }
            // return the users email.
            return $githubUser['email'];
        } catch (\Exception $e) {
            // do nothing
            // email not found
            // place 'Unknown Email' instead since null
            return null;
        }
    }


    /**
     * * sortOrganizationContributorsByCommits()
     * 
     *  This method utilizes a helper function to sort the
     *  contributors by commits. 
     * 
     *  $this->organization['contributor] is a collection.
     * 
     * @return Void
     */
    public function sortOrganizationContributorsByCommits()
    {
        return $this->organization['contributors']->sortByDesc('commits');
    }


    /**
     * * paginateContributors(Request $request)
     * 
     *  This method is a custom attempt at pagination. 
     *  It verifies and sorts the collection a final time then 
     *  builds the contributor collection based on the requested page
     *  and limits which are defaulted to page 1 and a limit of 5.
     * 
     * @param Request $request
     * 
     * @return Collection $contributors
     */
    public function paginateContributors(Request $request)
    {
        $this->organization['contributors'] = $this->sortOrganizationContributorsByCommits();

        $contributors = collect([]);

        if (isset($request->limit)) {

            $this->limit = $request->limit;
        }

        if (isset($request->page)) {

            $startPosition = (($request->page - 1) * $this->limit);

            for ($position = $startPosition; $position < ($startPosition + $this->limit); $position++) {
                if (isset($this->organization['contributors'][$position])) {
                    $contributors->push($this->organization['contributors']->sortByDesc('commits')[$position]);
                }
            }
        } else {
            for ($position = 0; $position < $this->limit; $position++) {
                if (isset($this->organization['contributors'][$position])) {
                    $contributors->push($this->organization['contributors']->sortByDesc('commits')[$position]);
                }
            }
        }

        return $contributors;
    }
}
