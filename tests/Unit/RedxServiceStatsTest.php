<?php

namespace Alzaf\BdCourier\Tests\Unit;

use Alzaf\BdCourier\Services\FraudCheck\RedxService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RedxServiceStatsTest extends TestCase
{
    public function test_parcels_in_transit_are_reported_but_not_counted(): void
    {
        $stats = RedxService::statsFromPayload([
            'totalParcels' => 10,
            'deliveredParcels' => 6,
            'returnPercentage' => 20,
            'customerSegment' => 'Normal Customer',
        ]);

        $this->assertSame(6, $stats['success']);
        $this->assertSame(2, $stats['cancel']);
        $this->assertSame(8, $stats['total'], 'Only resolved parcels count');
        $this->assertSame(75.0, $stats['success_rate']);
        $this->assertSame(10, $stats['booked']);
        $this->assertSame(2, $stats['in_transit']);
        $this->assertSame('reported', $stats['return_source']);
        $this->assertSame('Normal Customer', $stats['customer_segment']);
        $this->assertSame('safe', $stats['risk_level']);
    }

    public function test_a_customer_whose_parcels_are_all_on_the_way_has_no_history_yet(): void
    {
        $stats = RedxService::statsFromPayload(['totalParcels' => 5, 'deliveredParcels' => 0, 'returnPercentage' => 0]);

        $this->assertSame(0, $stats['total']);
        $this->assertNull($stats['success_rate']);
        $this->assertSame(5, $stats['in_transit']);
        $this->assertSame('new_customer', $stats['risk_level']);
    }

    public function test_without_a_return_figure_every_undelivered_parcel_counts_as_before(): void
    {
        $stats = RedxService::statsFromPayload(['totalParcels' => 5, 'deliveredParcels' => 1]);

        $this->assertSame(1, $stats['success']);
        $this->assertSame(4, $stats['cancel']);
        $this->assertSame(5, $stats['total']);
        $this->assertSame(20.0, $stats['success_rate']);
        $this->assertSame(0, $stats['in_transit']);
        $this->assertSame('assumed', $stats['return_source']);
        $this->assertNull($stats['customer_segment']);
    }

    public function test_an_explicit_returned_count_outranks_the_percentage(): void
    {
        $stats = RedxService::statsFromPayload(['totalParcels' => 10, 'deliveredParcels' => 5, 'returnedParcels' => 3, 'returnPercentage' => 50]);

        $this->assertSame(3, $stats['cancel']);
        $this->assertSame(2, $stats['in_transit']);
        $this->assertSame(8, $stats['total']);
    }

    #[DataProvider('oddPayloads')]
    public function test_odd_payloads_never_produce_negative_or_inflated_counts(array $data, int $success, int $cancel, int $inTransit): void
    {
        $stats = RedxService::statsFromPayload($data);

        $this->assertSame($success, $stats['success']);
        $this->assertSame($cancel, $stats['cancel']);
        $this->assertSame($inTransit, $stats['in_transit']);
        $this->assertSame($success + $cancel, $stats['total']);
    }

    public static function oddPayloads(): iterable
    {
        yield 'return percentage larger than the undelivered share' => [['totalParcels' => 4, 'deliveredParcels' => 4, 'returnPercentage' => 50], 4, 0, 0];
        yield 'more delivered than booked' => [['totalParcels' => 3, 'deliveredParcels' => 7], 3, 0, 0];
        yield 'negative and non-numeric values' => [['totalParcels' => -2, 'deliveredParcels' => 'x', 'returnPercentage' => -5], 0, 0, 0];
        yield 'percentage above 100 is ignored' => [['totalParcels' => 6, 'deliveredParcels' => 2, 'returnPercentage' => 250], 2, 4, 0];
        yield 'numeric strings' => [['totalParcels' => '10', 'deliveredParcels' => '7', 'returnPercentage' => '10.0'], 7, 1, 2];
        yield 'empty payload' => [[], 0, 0, 0];
    }
}
