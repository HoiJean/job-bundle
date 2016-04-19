<?php
/*
* This file is part of the job-bundle package.
*
* (c) Hannes Schulz <hannes.schulz@aboutcoders.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Abc\Bundle\JobBundle\Tests\Integration\Job;

use Abc\Bundle\JobBundle\Job\ManagerInterface;
use Abc\Bundle\JobBundle\Job\Status;
use Abc\Bundle\JobBundle\Model\ScheduleManagerInterface;
use Abc\Bundle\JobBundle\Tests\DatabaseTestCase;
use Abc\Bundle\JobBundle\Tests\Fixtures\Job\TestResponse;
use Abc\Bundle\SchedulerBundle\Model\Schedule;

/**
 * @author Hannes Schulz <hannes.schulz@aboutcoders.com>
 */
class ManagerTest extends DatabaseTestCase
{
    public function testJobsCanLog()
    {
        $ticket = $this->getJobManager()->addJob('log', array('message'));

        $logs = $this->getJobManager()->getLogs($ticket);

        $this->assertContains('message', $logs);
    }

    public function testHandlesExceptionsThrownByJob()
    {
        $job = $this->getJobManager()->addJob('throw_exception', array('message', 100));

        $this->assertEquals(Status::ERROR(), $job->getStatus());
        $this->assertInstanceOf('Abc\Bundle\JobBundle\Job\ExceptionResponse', $job->getResponse());
        $this->assertEquals('message', $job->getResponse()->getMessage());
        $this->assertEquals(100, $job->getResponse()->getCode());
    }

    public function testJobCanSetResponse()
    {
        $expectedResponse = new TestResponse('foobar');
        $ticket           = $this->getJobManager()->addJob('set_response', array($expectedResponse));

        $response = $this->getJobManager()->get($ticket)->getResponse();

        $this->assertEquals($expectedResponse, $response);
    }

    public function testJobCanCreateSchedule()
    {
        $ticket = $this->getJobManager()->addJob('create_schedule', array('cron', '* * * * *'));

        $this->assertEquals(Status::SLEEPING(), $this->getJobManager()->get($ticket)->getStatus());

        $schedules = $this->getScheduleManager()->findSchedules();

        $this->assertCount(1, $schedules);

        /** @var Schedule $schedule */
        $schedule = $schedules[0];

        $this->assertEquals('cron', $schedule->getType());
        $this->assertEquals('* * * * *', $schedule->getExpression());
    }

    public function testJobCanUpdateSchedule()
    {
        // create scheduled job
        $schedule = $this->getScheduleManager()->create('cron', '* * * * *');

        $ticket = $this->getJobManager()->addJob('update_schedule', array('cron', '1 1 * * *'), $schedule);

        // process schedules
        $this->runConsole("abc:scheduler:process", array("--iteration" => 1));

        $this->getEntityManager()->clear();

        $schedules = $this->getScheduleManager()->findSchedules();

        $this->assertCount(1, $schedules);

        /** @var Schedule $schedule */
        $schedule = $schedules[0];

        $this->assertEquals('cron', $schedule->getType());
        $this->assertEquals('1 1 * * *', $schedule->getExpression());
    }

    public function testJobCanRemoveSchedule()
    {
        // create scheduled job
        $schedule = $this->getScheduleManager()->create('cron', '* * * * *');

        $ticket = $this->getJobManager()->addJob('remove_schedule', null, $schedule);

        $this->assertCount(1, $this->getScheduleManager()->findSchedules());
        $this->assertEquals(Status::REQUESTED(), $this->getJobManager()->get($ticket)->getStatus());

        // process schedules
        $this->runConsole("abc:scheduler:process", array("--iteration" => 1));

        $this->assertContains('removed schedule', $this->getJobManager()->getLogs($ticket));
        $this->assertEquals(Status::PROCESSED(), $this->getJobManager()->get($ticket)->getStatus());
        $this->assertEmpty($this->getScheduleManager()->findSchedules());
    }

    public function testCancelJobWithSchedule()
    {
        $schedule = new Schedule();
        $schedule->setExpression('* * * * *');
        $schedule->setType('cron');

        $ticket = $this->getJobManager()->addJob('schedule', array(1), $schedule);

        $this->getJobManager()->cancelJob($ticket);

        $this->assertEmpty($this->getJobManager()->getLogs($ticket));
        $this->assertEquals(Status::CANCELLED(), $this->getJobManager()->get($ticket)->getStatus());

        $this->assertEmpty($this->getScheduleManager()->findSchedules());
    }

    public function testScheduleIsDisabledIfJobThrowsException()
    {
        // create scheduled job that throws an exception
        $schedule = new Schedule();
        $schedule->setExpression('* * * * *');
        $schedule->setType('cron');

        $ticket = $this->getJobManager()->addJob('throw_exception', array('message', 100), $schedule);

        // process schedules
        $this->runConsole("abc:scheduler:process", array("--iteration" => 1));

        $this->assertEquals(Status::ERROR(), $this->getJobManager()->get($ticket)->getStatus());

        $schedules = $this->getScheduleManager()->findSchedules();

        $this->assertCount(0, $schedules);
    }

    public function testJobCanManageJobs()
    {
        $job = $this->getJobManager()->addJob('manage_job');

        $ticket = $job->getResponse();

        $logs = $this->getJobManager()->getJobLogs($ticket);

        $this->assertContains('addedJob', $logs);
    }

    /**
     * @return ManagerInterface
     */
    protected function getJobManager()
    {
        return $this->getContainer()->get('abc.job.manager');
    }

    /**
     * @return ScheduleManagerInterface
     */
    protected function getScheduleManager()
    {
        return $this->getContainer()->get('abc.job.schedule_manager');
    }
}