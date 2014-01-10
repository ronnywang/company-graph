<?php

class IndexController extends Pix_Controller
{
    public function indexAction()
    {
        $this->view->id = intval($_GET['id']);
    }

    public function jsonAction()
    {
        return $this->json(GraphLib::getJSONFromID(intval($_GET['id'])));
    }
}
