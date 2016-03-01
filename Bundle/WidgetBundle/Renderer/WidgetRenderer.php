<?php

namespace Victoire\Bundle\WidgetBundle\Renderer;

use Symfony\Component\DependencyInjection\Container;
use Victoire\Bundle\BusinessPageBundle\Entity\BusinessPage;
use Victoire\Bundle\CoreBundle\Entity\View;
use Victoire\Bundle\CoreBundle\Event\WidgetRenderEvent;
use Victoire\Bundle\CoreBundle\VictoireCmsEvents;
use Victoire\Bundle\WidgetBundle\Cache\WidgetCache;
use Victoire\Bundle\WidgetBundle\Entity\Widget;
use Victoire\Bundle\WidgetMapBundle\Entity\Slot;
use Victoire\Bundle\WidgetMapBundle\Helper\WidgetMapHelper;

class WidgetRenderer
{
    private $container;
    /**
     * @var WidgetCache
     */
    private $widgetCache;

    /**
     * WidgetRenderer constructor.
     *
     * @param Container   $container
     * @param Client      $redis
     * @param WidgetCache $widgetCache
     */
    public function __construct(Container $container, WidgetCache $widgetCache)
    {
        $this->container = $container;
        $this->widgetCache = $widgetCache;
    }

    /**
     * render the Widget.
     *
     * @param Widget $widget
     * @param View   $view
     *
     * @return widget show
     */
    public function render(Widget $widget, View $view)
    {
        //the mode of display of the widget
        $mode = $widget->getMode();

        //if entty is given and it's not the object, retrive it and set the entity for the widget
        if ($mode == Widget::MODE_BUSINESS_ENTITY && $view instanceof BusinessPage) {
            $widget->setEntity($view->getBusinessEntity());
        }

        //the templating service
        $templating = $this->container->get('victoire_templating');

        //the content of the widget
        $parameters = $this->container->get('victoire_widget.widget_content_resolver')->getWidgetContent($widget);

        //the template displayed is in the widget bundle (with the potential theme)
        $showView = 'show'.ucfirst($widget->getTheme());
        $templateName = $this->container->get('victoire_widget.widget_helper')->getTemplateName($showView, $widget);

        return $templating->render(
            $templateName,
            $parameters
        );
    }

    /**
     * render a widget.
     *
     * @param Widget $widget
     * @param View   $view
     *
     * @return string
     */
    public function renderContainer(Widget $widget, View $view)
    {
        $dispatcher = $this->container->get('event_dispatcher');

        $dispatcher->dispatch(VictoireCmsEvents::WIDGET_PRE_RENDER, new WidgetRenderEvent($widget));

        $widgetMap = WidgetMapHelper::getWidgetMapByWidgetAndView($widget, $view);

        $directive = '';
        if ($this->container->get('security.context')->isGranted('ROLE_VICTOIRE')) {
            $directive = 'widget';
        }

        $html = sprintf('<div %s widget-map="%s" class="vic-widget-container" data-id="%s">', $directive, $widgetMap->getId(), $widget->getId());

        $content = $this->widgetCache->fetch($widget);
        if (null === $content) {
            $content = $this->render($widget, $view);
            $this->widgetCache->save($widget, $content);
        }
        $html .= $content;
        $html .= '</div>';

        $dispatcher->dispatch(VictoireCmsEvents::WIDGET_POST_RENDER, new WidgetRenderEvent($widget, $html));

        return $html;
    }

    /**
     * prepare a widget to be rendered asynchronously.
     *
     * @param int $widgetId
     *
     * @return string
     */
    public function prepareAsynchronousRender($widgetId)
    {
        $ngControllerName = 'widget'.$widgetId.'AsynchronousLoadCtrl';
        $ngDirectives = sprintf('ng-controller="WidgetAsynchronousLoadController as %s" class="vic-widget" ng-init="%s.init(%d)" ng-bind-html="html"', $ngControllerName, $ngControllerName, $widgetId);
        $html = sprintf('<div class="vic-widget-container vic-widget-asynchronous" data-id="%d" %s></div>', $widgetId, $ngDirectives);

        return $html;
    }

    /**
     * render widget unlink action.
     *
     * @param int  $widgetId
     * @param View $view
     *
     * @return string
     */
    public function renderUnlinkActionByWidgetId($widgetId, $view)
    {
        return $this->container->get('victoire_templating')->render(
            'VictoireCoreBundle:Widget:widgetUnlinkAction.html.twig',
            [
                'widgetId' => $widgetId,
                'view'     => $view,
            ]
        );
    }

    /**
     * Compute slot options.
     *
     * @param Slot  $slotId
     * @param array $options
     *
     * @return string
     */
    public function computeOptions($slotId, $options = [])
    {
        $slots = $this->container->getParameter('victoire_core.slots');

        $availableWidgets = $this->container->getParameter('victoire_core.widgets');
        $widgets = [];

        //If the slot is declared in config
        if (!empty($slots[$slotId]) && !empty($slots[$slotId]['widgets'])) {
            //parse declared widgets
            $slotWidgets = array_keys($slots[$slotId]['widgets']);
        } elseif (!empty($options['availableWidgets'])) {
            $slotWidgets = $options['availableWidgets'];
        } else {
            //parse all widgets
            $slotWidgets = array_keys($availableWidgets);
        }

        foreach ($slotWidgets as $slotWidget) {
            $widgetParams = $availableWidgets[$slotWidget];
            $widgets[$slotWidget]['params'] = $widgetParams;
        }
        $slots[$slotId]['availableWidgets'] = $widgets;
        if (isset($options['max'])) {
            $slots[$slotId]['max'] = $options['max'];
        }

        return $slots[$slotId];
    }

    /**
     * Get the extra classes for the css.
     *
     * @return string The classes
     */
    public function getExtraCssClass(Widget $widget)
    {
        $cssClass = 'vic-widget-'.strtolower($this->container->get('victoire_widget.widget_helper')->getWidgetName($widget));

        return $cssClass;
    }

}
