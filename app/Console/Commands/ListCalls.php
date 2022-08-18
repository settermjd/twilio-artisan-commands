<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use Twilio\Exceptions\EnvironmentException;
use Twilio\Rest\Api\V2010\Account\CallInstance;
use Twilio\Rest\Client;

class ListCalls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'twilio:list-calls {--short-date : Display all dates in the short form}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List calls on your Twilio account';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $client = new Client(
            env('TWILIO_ACCOUNT_SID'),
            env('TWILIO_AUTH_TOKEN')
        );

        try {
            $calls = $client->calls->read();
        } catch (EnvironmentException $e) {
            $this->error("Unable to retrieve calls from your account");
            return -1;
        }

        if (empty($calls)) {
            $this->info("No calls are available on your account");
        }

        $dateFormat = $this->option('short-date') ? 'd/m/Y H:i' : 'r';

        $callDetails = [];
        foreach ($calls as $call) {
            $callDetails[] = [
                $call->sid,
                $call->dateCreated->format($dateFormat),
                $call->to,
                ucfirst($call->status),
                $call->startTime->format($dateFormat),
                $call->endTime->format($dateFormat),
                $this->formatCallPrice($call),
                $call->priceUnit
            ];
        }

        $this->info("The current list of calls available on your account:");

        $this->table(
            [
                'Call ID',
                'Created On',
                'Recipient',
                'Status',
                'Started At',
                'Ended At',
                'Price',
                'Price Unit'
            ],
            $callDetails,
        );

        $this->info(sprintf("Total calls: %d", count($calls)));

        return 0;
    }

    private function formatCallPrice(CallInstance $call, string $locale = 'en_US'): string
    {
        $price = floatval(abs($call->price)) * 100;
        $money = new Money($price, new Currency($call->priceUnit));
        $currencies = new ISOCurrencies();
        $numberFormatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        $moneyFormatter = new IntlMoneyFormatter($numberFormatter, $currencies);

        return $moneyFormatter->format($money);
    }
}
