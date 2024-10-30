<?php


namespace HBAM\V1\Plugin\Repository;


class Cron
{
    /**
     * @var $term string
     *                      hourly
     *                      twicedaily
     *                      daily
     */
    protected $term;

    /**
     * @var callable
     */
    protected $func;

    protected $event_name;

    /**
     * Cron constructor.
     * @param $file string
     * @param $function callable
     * @param $term string
     * @param $event_name string
     */
    public function __construct($file, $function, $term, $event_name)
    {
        $this->func = $function;
        $this->term = $term;
        $this->event_name = $event_name;

        add_action($this->event_name, $this->func);

        register_activation_hook($file, [$this, 'my_activation']);
        register_deactivation_hook($file, [$this, 'my_deactivation']);
    }

    public function my_activation()
    {
        wp_schedule_event(time(), $this->term, $this->event_name);
    }

    public function my_deactivation()
    {
        wp_clear_scheduled_hook($this->event_name);
    }

    public function change_term($term){
        wp_clear_scheduled_hook($this->event_name);
        wp_schedule_event(time(), $term, $this->event_name);
    }
}