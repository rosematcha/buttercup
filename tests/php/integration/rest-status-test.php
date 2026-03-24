<?php

declare(strict_types=1);

class Buttercup_REST_Status_Test extends WP_UnitTestCase
{
	public function set_up(): void
	{
		parent::set_up();

		$user_id = self::factory()->user->create([
			'role' => 'editor',
		]);
		wp_set_current_user($user_id);

		$mast_post_id = self::factory()->post->create([
			'post_status' => 'publish',
			'post_title' => 'Mast Post',
		]);
		wp_set_post_tags($mast_post_id, ['mast']);

		$home_post_id = self::factory()->post->create([
			'post_status' => 'publish',
			'post_title' => 'Home Post',
		]);
		wp_set_post_tags($home_post_id, ['home']);

		$dual_post_id = self::factory()->post->create([
			'post_status' => 'publish',
			'post_title' => 'Dual Post',
		]);
		wp_set_post_tags($dual_post_id, ['mast', 'home']);

		$showcase_post_id = self::factory()->post->create([
			'post_status' => 'publish',
			'post_title' => 'Feature Post',
		]);
		wp_set_post_tags($showcase_post_id, ['feature']);
	}

	public function tear_down(): void
	{
		wp_set_current_user(0);
		parent::tear_down();
	}

	public function test_homepage_feed_status_endpoint_returns_expected_shape_and_counts(): void
	{
		$request = new WP_REST_Request('GET', '/buttercup/v1/homepage-feed-status');
		$request->set_param('mastTagSlug', 'mast');
		$request->set_param('homeTagSlug', 'home');

		$response = rest_do_request($request);
		$this->assertSame(200, $response->get_status());

		$data = $response->get_data();

		$this->assertArrayHasKey('mastTagSlug', $data);
		$this->assertArrayHasKey('homeTagSlug', $data);
		$this->assertArrayHasKey('mastCount', $data);
		$this->assertArrayHasKey('homeCount', $data);
		$this->assertArrayHasKey('mastOverflow', $data);
		$this->assertArrayHasKey('homeOverflow', $data);
		$this->assertArrayHasKey('mastSelected', $data);
		$this->assertArrayHasKey('homeSelected', $data);
		$this->assertArrayHasKey('dualTagged', $data);

		$this->assertGreaterThan(0, intval($data['mastCount']));
		$this->assertGreaterThan(0, intval($data['homeCount']));
	}

	public function test_tag_showcase_status_endpoint_handles_empty_nonmatching_and_matching_filters(): void
	{
		$empty_request = new WP_REST_Request('GET', '/buttercup/v1/tag-showcase-status');
		$empty_response = rest_do_request($empty_request);
		$this->assertSame(200, $empty_response->get_status());
		$this->assertFalse((bool) $empty_response->get_data()['hasResults']);

		$non_match_request = new WP_REST_Request('GET', '/buttercup/v1/tag-showcase-status');
		$non_match_request->set_param('tagSlugs', 'does-not-exist');
		$non_match_request->set_param('postTypes', 'post');
		$non_match_response = rest_do_request($non_match_request);
		$this->assertSame(200, $non_match_response->get_status());
		$this->assertFalse((bool) $non_match_response->get_data()['hasResults']);

		$match_request = new WP_REST_Request('GET', '/buttercup/v1/tag-showcase-status');
		$match_request->set_param('tagSlugs', 'feature');
		$match_request->set_param('postTypes', 'post');
		$match_response = rest_do_request($match_request);
		$this->assertSame(200, $match_response->get_status());
		$this->assertTrue((bool) $match_response->get_data()['hasResults']);
		$this->assertGreaterThan(0, intval($match_response->get_data()['count']));
	}
}
