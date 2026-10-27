<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

$campaignId = $argv[1] ?? 'CAMP-001';
$promoCode  = $argv[2] ?? 'PROMO15';

$db = (new Lab\Database())->connect();
$preparer = new Lab\CampaignPreparer($db);
$created = $preparer->prepare($campaignId, $promoCode);

if ($created === 0) {
    echo "Campaign {$campaignId} already has 50 prepared requests. Original selection preserved." . PHP_EOL;
} else {
    echo "Prepared {$created} PENDING requests for campaign {$campaignId} with promo code {$promoCode}." . PHP_EOL;
}