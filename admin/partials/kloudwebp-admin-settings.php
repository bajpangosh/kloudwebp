<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors('kloudwebp_messages'); ?>

    <div class="kloudwebp-settings">
        <form method="post" action="options.php">
            <?php
            settings_fields('kloudwebp_settings');
            do_settings_sections('kloudwebp_settings');
            submit_button();
            ?>
        </form>
    </div>
</div>

<style>
.kloudwebp-settings {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    max-width: 800px;
    margin-top: 20px;
}

.kloudwebp-settings .form-table th {
    padding: 20px;
    width: 250px;
}

.kloudwebp-settings .form-table td {
    padding: 20px;
    vertical-align: top;
    padding-top: 15px;
}

.kloudwebp-settings .form-table label {
    display: block;
    margin-bottom: 5px;
}

.kloudwebp-settings .form-table input[type="number"] {
    width: 100px;
}

.kloudwebp-settings .description {
    color: #646970;
    font-style: italic;
    margin-top: 4px;
}
</style>
