<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

/**
 * The document types the barangay issues.
 *
 * Seeded rather than read from config, because the Admin settings screen can now
 * change this list. The keys are the ones the previous configuration used, so
 * anything already recorded against them still lines up.
 *
 * Safe to re-run: a type that already exists is updated rather than duplicated,
 * so a re-seed on a populated database converges instead of failing.
 */
class DocumentTypeSeeder extends Seeder
{
    /**
     * @var list<array{key: string, label: string, description: string, fee: string, icon: string, tone: string}>
     */
    private const TYPES = [
        [
            'key' => 'barangay_clearance',
            'label' => 'Barangay Clearance',
            'description' => 'Certifies that a resident has no derogatory record in the barangay. Commonly required for employment, business, and other local transactions.',
            'fee' => '100.00',
            'icon' => 'clipboard',
            'tone' => 'navy',
        ],
        [
            'key' => 'certificate_of_indigency',
            'label' => 'Certificate of Indigency',
            'description' => 'Issued to residents in financial need as supporting proof for aid, scholarship, and medical assistance programs.',
            'fee' => '0.00',
            'icon' => 'heart-hands',
            'tone' => 'sky',
        ],
        [
            'key' => 'first_time_jobseeker_certificate',
            'label' => 'First-Time Jobseeker Certificate',
            'description' => 'Issued to first-time job applicants in support of the First Time Jobseekers Assistance Act (RA 11961).',
            'fee' => '50.00',
            'icon' => 'briefcase',
            'tone' => 'gold',
        ],
        [
            'key' => 'barangay_residency_certificate',
            'label' => 'Barangay Residency Certificate',
            'description' => 'Confirms that a person is a bona fide resident of the barangay, required for various government and private transactions.',
            'fee' => '100.00',
            'icon' => 'home',
            'tone' => 'navy',
        ],
    ];

    public function run(): void
    {
        foreach (self::TYPES as $index => $type) {
            DocumentType::updateOrCreate(
                ['key' => $type['key']],
                [
                    'label' => $type['label'],
                    'description' => $type['description'],
                    'fee_amount' => $type['fee'],
                    'icon' => $type['icon'],
                    'tone' => $type['tone'],
                    'is_active' => true,
                    'sort_order' => $index,
                ]
            );
        }
    }
}
