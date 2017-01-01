<?php
/**
 * @file
 * Contains \Drupal\hello_world\Controller\HelloController.
 */
namespace Drupal\myvolumio\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\myvolumio;

class myvolumioController  extends ControllerBase {

  public function test() {
    return array(
      '#type' => 'markup',
      '#markup' => \Drupal\myvolumio\myvolumio_test_api(),
    );
  }

  public function backupMyWebRadio() {

      $node = \Drupal\myvolumio\backupMyWebRadio();

      return $this->redirect('entity.node.edit_form', ['node' => $node]);
  }

    public function backupMyRadioFavourites() {

        $node = \Drupal\myvolumio\backupMyRadioFavourites();

        return $this->redirect('entity.node.edit_form', ['node' => $node]);
    }

    public function backupMyPlaylists() {

        $node = \Drupal\myvolumio\backupMyPlaylists();

        return $this->redirect('entity.node.edit_form', ['node' => $node]);
    }

    public function backupMyConfig() {

        $node = \Drupal\myvolumio\backupMyConfig();

        return $this->redirect('entity.node.edit_form', ['node' => $node]);
    }

}