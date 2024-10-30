<?php


namespace HBAM\V1\Plugin\Repository;



use HBAM\V1\Plugin\Classes\Option;

class OptionPage
{
    public function __construct()
    {

    }

    /**
     * @param $page_title string
     * @param $menu_title string
     * @param $capability string
     * @param $menu_slug string
     * @param $function callable
     * @param $position int
     *
     * @return Option
     */
    public function create($page_title, $menu_title, $capability, $menu_slug, $function, $position)
    {
        add_action('admin_menu', function () use ($page_title, $menu_title, $capability, $menu_slug, $function, $position) {
            add_submenu_page(
                "options-general.php",
                $page_title,
                $menu_title,
                $capability,
                $menu_slug,
                $function,
                $position
            );
        });

        return new Option($page_title, $menu_title, $capability, $menu_slug, $position);
    }
}