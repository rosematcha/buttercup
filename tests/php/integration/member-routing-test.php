<?php

declare(strict_types=1);

class Buttercup_Member_Routing_Test extends WP_UnitTestCase
{
	private string $original_request_uri = '';
	private int $page_id = 0;
	private string $page_slug = '';
	private string $request_path = '';

	public function set_up(): void
	{
		parent::set_up();

		$this->original_request_uri = $_SERVER['REQUEST_URI'] ?? '';
		$this->page_slug = 'team-' . wp_generate_password(8, false, false);

		$this->page_id = self::factory()->post->create([
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_name' => $this->page_slug,
			'post_title' => 'Team',
			'post_content' => '<!-- wp:buttercup/team {"enableMemberPages":true} --><!-- wp:buttercup/team-member {"name":"Alice Doe"} /--><!-- /wp:buttercup/team -->',
		]);

		buttercup_refresh_member_bases();

		$this->request_path = '/' . $this->page_slug . '/alice';
		buttercup_get_member_match_cache(ltrim($this->request_path, '/'), true);
	}

	public function tear_down(): void
	{
		if ($this->original_request_uri === '') {
			unset($_SERVER['REQUEST_URI']);
		} else {
			$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		}

		parent::tear_down();
	}

	public function test_valid_member_path_maps_to_page_id_and_member_slug(): void
	{
		$wp = new WP();
		$wp->query_vars = [];
		$_SERVER['REQUEST_URI'] = $this->request_path;

		buttercup_parse_member_request($wp);

		$this->assertSame($this->page_id, intval($wp->query_vars['page_id'] ?? 0));
		$this->assertSame($this->page_slug, (string) ($wp->query_vars['pagename'] ?? ''));
		$this->assertSame('alice', (string) ($wp->query_vars['buttercup_member'] ?? ''));
	}

	public function test_invalid_incoming_member_slug_is_rejected(): void
	{
		$wp = new WP();
		$wp->query_vars = [
			'buttercup_member' => '%%%'
		];
		$_SERVER['REQUEST_URI'] = '/not-a-member-route';

		buttercup_parse_member_request($wp);

		$this->assertArrayNotHasKey('buttercup_member', $wp->query_vars);
	}

	public function test_canonical_redirect_is_disabled_for_valid_member_url(): void
	{
		global $wp_query;
		if ($wp_query instanceof WP_Query) {
			$wp_query->set('buttercup_member', '');
		}

		$requested_url = home_url($this->page_slug . '/alice');
		$redirect = buttercup_disable_member_canonical('https://example.org/redirect', $requested_url);

		$this->assertFalse($redirect);
	}
}
