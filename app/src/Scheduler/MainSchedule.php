<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Enum\ProviderEnum;
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
        //        $schedule->add(
        //            RecurringMessage::cron('0 10 * * *', new FetchRateMessage(
        //                new \DateTimeImmutable(),
        //                ProviderEnum::CBR,
        //            ))
        //        );

        return $schedule;
    }
}
