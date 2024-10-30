<?php


namespace HBAM\V1\Plugin\Classes;


class Option
{
    /**
     * @var $page_title string
     */
    protected $page_title;

    /**
     * @var $menu_title string
     */
    protected $menu_title;

    /**
     * @var $capability string
     */
    protected $capability;

    /**
     * @var $menu_slug string
     */
    protected $menu_slug;


    /**
     * @var $position int
     */
    protected $position;


    /**
     * @param $page_title string
     * @param $menu_title string
     * @param $capability string
     * @param $menu_slug string
     * @param $position int
     */
    public function __construct($page_title, $menu_title, $capability, $menu_slug, $position)
    {
        $this->page_title = $page_title;
        $this->menu_title = $menu_title;
        $this->capability = $capability;
        $this->menu_slug = $menu_slug;
        $this->position = $position;
    }

    /**
     * @param $option_slug string
     * @param $option_name string
     * @param $description string
     * @param $placeholder string
     */
    public function register_option($option_slug, $option_name, $description, $placeholder)
    {
        add_action('admin_init', function () use ($option_slug, $option_name, $description, $placeholder) {
            add_settings_section(
                $option_slug,
                $option_name,
                function () use ($description) {
                    echo '<p>' . $description . '</p>';
                },
                $this->menu_slug
            );


            $option_field_slug = $option_slug . '-option';
            add_settings_field(
                $option_field_slug,
                $option_name,
                function () use ($option_field_slug, $placeholder) {
                    echo '<input name="' . $option_field_slug . '" id="' . $option_field_slug . '" type="text" value="' . get_option($option_field_slug) . '" placeholder="' . $placeholder . '" class="regular-text" />';
                },
                $this->menu_slug,
                $option_slug
            );


            register_setting($this->menu_slug, $option_field_slug, function ($value) {
                preg_match('/^https?:\/\/[^\/]+/', esc_url($value), $matches);
                return $matches[0] ?? "";
            });
        });
    }

    /**
     * @param $option_slug string
     * @param $option_name string
     * @param $description string
     * @param $options array
     */
    public function register_option_select($option_slug, $option_name, $description, $options)
    {
        add_action('admin_init', function () use ($option_slug, $option_name, $description, $options) {
            add_settings_section(
                $option_slug,
                $option_name,
                function () use ($description) {
                    echo '<p>' . $description . '</p>';
                },
                $this->menu_slug
            );


            $option_field_slug = $option_slug . '-option';
            $current = get_option($option_field_slug);
            add_settings_field(
                $option_field_slug,
                $option_name,
                function () use ($option_field_slug, $options, $current) {
                    echo '<select name="' . $option_field_slug . '" id="' . $option_field_slug . '" class="regular-text">';

                    foreach ($options as $option) {
                        echo '<option value="' . $option['value'] . '" ' . ($current === $option['value'] ? 'selected' : '') . '>' . $option['label'] . '</option>';
                    }

                    echo "</select>";
                },
                $this->menu_slug,
                $option_slug
            );


            register_setting($this->menu_slug, $option_field_slug);
        });
    }
}