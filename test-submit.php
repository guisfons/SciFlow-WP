<?php

$post_id = 93; // Assume we need a post ID, let's find one or create one
$user = wp_get_current_user();
if (!$user->ID) {
    // login as admin
    $user = get_user_by('login', 'admin') ?: get_users()[0];
    wp_set_current_user($user->ID);
}

// 1. Create a dummy article
$post_id = wp_insert_post(array(
    'post_title' => 'Test Article',
    'post_type' => 'enfrute_trabalhos',
    'post_status' => 'publish'
));

update_post_meta($post_id, '_sciflow_reviewer_id', $user->ID);
update_post_meta($post_id, '_sciflow_status', 'em_avaliacao');
update_post_meta($post_id, '_sciflow_event', 'enfrute');

$status_manager = new SciFlow_Status_Manager();
$email = new SciFlow_Email();
$review = new SciFlow_Review($status_manager, $email);
$editorial = new SciFlow_Editorial($status_manager, $email);

echo "Initial status: " . $status_manager->get_status($post_id) . "\n";

// 2. Submit review first time
$data = array(
    'decision' => 'approved_with_considerations',
    'scores' => array(
        'originalidade' => 10,
        'objetividade' => 10,
        'organizacao' => 10,
        'metodologia' => 10,
        'aderencia' => 10,
    ),
    'notes' => 'Test notes'
);

$res1 = $review->submit_review($post_id, $data);
echo "Submit 1 Result: " . (is_wp_error($res1) ? $res1->get_error_message() : 'Success') . "\n";
echo "Status after submit 1: " . $status_manager->get_status($post_id) . "\n";

// 3. Editor returns to reviewer
echo "Editor returns to reviewer...\n";
$res_edit = $editorial->make_decision($post_id, 'return_to_reviewer', 'Please review again');
echo "Editor Return Result: " . (is_wp_error($res_edit) ? $res_edit->get_error_message() : 'Success') . "\n";
echo "Status after return: " . $status_manager->get_status($post_id) . "\n";

// 4. Submit review SECOND time with SAME data
$res2 = $review->submit_review($post_id, $data);
echo "Submit 2 Result: " . (is_wp_error($res2) ? $res2->get_error_message() : 'Success') . "\n";
echo "Status after submit 2: " . $status_manager->get_status($post_id) . "\n";

// cleanup
wp_delete_post($post_id, true);
