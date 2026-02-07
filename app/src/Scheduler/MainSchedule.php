<?php

namespace App\Scheduler;

use App\Enum\RateSource;
use App\Message\FetchRateMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('main')]
class MainSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();
        // Пример работы крона (Загрузка утреннего курса)
        $schedule->add(
            RecurringMessage::cron('0 10 * * *', new FetchRateMessage(
                new \DateTimeImmutable(),
                RateSource::CBR,
            ))
        );

        return $schedule;
    }
}
