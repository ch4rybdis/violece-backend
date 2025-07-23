<?php


namespace Tests\Unit\Psychology;

use Tests\TestCase;
use App\Services\Psychology\PsychologicalScoringService;
use App\Models\Psychology\UserPsychologicalProfile;
use App\Models\Psychology\PsychologyQuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ScoringServiceTest extends TestCase
{
use RefreshDatabase;

protected PsychologicalScoringService $scoringService;

protected function setUp(): void
{
parent::setUp();
$this->scoringService = app(PsychologicalScoringService::class);
}

/** @test */
public function it_calculates_trait_descriptions_correctly()
{
$highOpenness = $this->scoringService->getTraitDescription('openness', 85);
$lowOpenness = $this->scoringService->getTraitDescription('openness', 15);
$moderateOpenness = $this->scoringService->getTraitDescription('openness', 50);

$this->assertStringContainsString('creative', strtolower($highOpenness));
$this->assertStringContainsString('routine', strtolower($lowOpenness));
$this->assertStringContainsString('balanced', strtolower($moderateOpenness));
}

/** @test */
public function it_calculates_attachment_descriptions_correctly()
{
$secureDesc = $this->scoringService->getAttachmentDescription('secure');
$anxiousDesc = $this->scoringService->getAttachmentDescription('anxious');
$avoidantDesc = $this->scoringService->getAttachmentDescription('avoidant');

$this->assertStringContainsString('comfortable', strtolower($secureDesc));
$this->assertStringContainsString('worry', strtolower($anxiousDesc));
$this->assertStringContainsString('independence', strtolower($avoidantDesc));
}

/** @test */
public function it_generates_ideal_partner_traits_based_on_profile()
{
$profile = UserPsychologicalProfile::factory()->make([
'agreeableness_score' => 85,
'conscientiousness_score' => 75,
'neuroticism_score' => 80,
'primary_attachment_style' => 'anxious'
]);

$idealTraits = $this->scoringService->generateIdealPartnerTraits($profile);

$this->assertIsArray($idealTraits);
$this->assertArrayHasKey('high_agreeableness', $idealTraits);
$this->assertArrayHasKey('low_neuroticism', $idealTraits); // Should prefer low neuroticism
$this->assertArrayHasKey('secure_attachment', $idealTraits); // Anxious benefits from secure
}

/** @test */
public function it_predicts_relationship_styles_accurately()
{
// Test secure + agreeable = collaborative
$profile1 = UserPsychologicalProfile::factory()->make([
'secure_attachment_score' => 85,
'agreeableness_score' => 80,
'primary_attachment_style' => 'secure'
]);

$style1 = $this->scoringService->getPredictedRelationshipStyle($profile1);
$this->assertStringContainsString('collaborative', strtolower($style1));

// Test high extraversion + openness = adventurous
$profile2 = UserPsychologicalProfile::factory()->make([
'extraversion_score' => 85,
'openness_score' => 75
]);

$style2 = $this->scoringService->getPredictedRelationshipStyle($profile2);
$this->assertStringContainsString('adventurous', strtolower($style2));

// Test high conscientiousness + low neuroticism = stable
$profile3 = UserPsychologicalProfile::factory()->make([
'conscientiousness_score' => 85,
'neuroticism_score' => 20,
'primary_attachment_style' => 'secure'
]);

$style3 = $this->scoringService->getPredictedRelationshipStyle($profile3);
$this->assertStringContainsString('stable', strtolower($style3));
}
}
