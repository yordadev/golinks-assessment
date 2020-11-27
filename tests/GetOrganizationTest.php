<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class GetOrganizationTest extends TestCase
{
    /**
     * Test a successfull failure validation missing organization param.
     * 
     * Expecting a status code of 404 - Not Found
     *
     * @return void
     */
    public function testFailureRequestMissingOrganizion()
    {
        $this->get('v1/organization', ['Acceept' => 'application/json']);

        $this->assertResponseStatus(404);
    }


    /**
     * Test a successful failure catch with an unknown fake organization
     *
     * Expecting a status code of 404 - Not Found
     * 
     * @return void
     */
    public function testFailureRequestUnknownOrganizion()
    {
        $this->get('v1/organization?organization=00gkd9djaifhjg9g', ['Acceept' => 'application/json']);

        $this->assertResponseStatus(404);
    }


    /**
     * Test a successful GET request using ploi-deploy organization
     * 
     * Expecting a status code of 200 - OK
     * 
     * @return void
     */
    public function testSuccessRequestOrganizion()
    {
        $this->get('v1/organization?organization=ploi-deploy', ['Acceept' => 'application/json']);

        $this->assertResponseStatus(200);
    }


    /**
     * Test a successfull pagination page 2 using ploi-deply organization
     * 
     * Expecting a status code of 200 - OK
     * 
     * @return void
     */
    public function testSuccessRequestOrganizionPage2()
    {
        $this->get('v1/organization?organization=ploi-deploy&page=2', ['Acceept' => 'application/json']);

        $this->assertResponseStatus(200);
    }


    /**
     * Test a successfull query modifying the limit to 100 from default 5
     * 
     * Expecting a status code of 200 - OK
     *
     * @return void
     */
    public function testSuccessRequestOrganizionLimit100()
    {
        $this->get('v1/organization?organization=ploi-deploy&limit=100', ['Acceept' => 'application/json']);

        $this->assertResponseStatus(200);
    }
}
