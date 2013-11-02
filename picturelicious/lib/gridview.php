<?php

/*
  This class takes an array of images and sorts them into a grid view.
  This is the equivalent of the javascript gridSolve function in picturelicious.js
  with the difference that it runs server side of course.
*/

require_once( 'lib/config.php' );


function uasort_copy( $array, $comparator_callback )
{
  $rv = uasort($array, $comparator_callback);
  return $rv ? $array : null;
}


class GridView {

  public $height = 0;
  protected $grid = array();
  protected $gridWidth = 20;

  public function __construct( $gridWidth = 20 ) {
    $this->gridWidth = $gridWidth;
    $this->grid = array_fill( 0, $this->gridWidth, 0 );
  }

  // solve expects an array with thumbnails with the keys name and score
  public function solve( $thumbs )
  {
    assert(is_array($thumbs));

    // Get thumb indices sorted by thumb importance ("score").
    $thumbs_importance =
      array_keys(uasort_copy($thumbs, __CLASS__.'::__compareImageRating'));

    // How many thumbs get which CSS-Class? The first n thumbnails
    // get the biggest box etc.
    $classes = Config::$gridView['classes'];
    $total = count( $thumbs );
    $classCounts = array();
    $currentCount = 0;
    foreach( $classes as $className => $gc ) {
      $currentCount += $gc['percentage'] * $total;
      $classCounts[$className] =  ceil( $currentCount );
    }

    // Assign a CSS-Class to each thumb
    $pathPrefix = Config::$absolutePath . Config::$images['thumbPath'];
    $currentMax = 0;
    $currentClass = '';
    $j = 0;
    foreach ($thumbs_importance as $i) {
      if( $j++ >= $currentMax ) {
        list( $currentClass, $currentMax ) = each( $classCounts );
      }

      $t = $thumbs[$i];
      $t->gridData = array('class' => $currentClass);
    }

    // Now that every thumb has a CSS-Class, we can sort them into our grid
    $gridSize = Config::$gridView['gridSize'];
    foreach ($thumbs as $t) {
      $g = &$t->gridData;
      list($g['left'], $g['top']) =
        $this->insert($classes[$g['class']]['width'],
          $classes[$g['class']]['height']);
    }

    // Calculate the final grid height
    $this->height = max($this->height, max($this->grid));

    return $thumbs;
  }


  // Callback for uasort()
  public static function __compareImageRating( Image $a, Image $b )
  {
    $rv = $a->rating - $b->rating;
    if (!$rv)
      $rv = $a->votecount - $b->votecount;
    return ($rv > 0) - ($rv < 0);
  }

  protected function insert( $boxWidth, $boxHeight ) {

    // Height of the grid
    $maxHeight = 0;

    // Height of the grid at the last position
    $currentHeight = $this->grid[0];

    // Find free spots within the grid and collect them in an arry
    // A spot is a area in the grid with equal height
    $spotWidth = 0; // Width of the spot in grid units
    $spotLeft = 0; // Position in the grid (relative to left border)
    $freeSpots = array();

    for( $i = 0; $i < $this->gridWidth; $i++ ) {

      // Height is the same as at the last position?
      // -> increase the size of this spot
      if( $currentHeight == $this->grid[$i] ) {
        $spotWidth++;
      }

      // The height is different from the last position, and our current spot
      // is wider than 0
      if( ( $currentHeight != $this->grid[$i] || $i+1 == $this->gridWidth) && $spotWidth > 0 ) {
        $freeSpots[] = array( 'width' =>$spotWidth, 'left' => $spotLeft, 'height' => $currentHeight );
        $spotWidth = 1;
        $spotLeft = $i;

        // Make sure we don't miss the last one
        if( $currentHeight != $this->grid[$i] && $i+1 == $this->gridWidth ) {
          $freeSpots[] = array( 'width' =>$spotWidth, 'left' => $spotLeft, 'height' => $this->grid[$i] );
        }
      }

      $currentHeight = $this->grid[$i];
      $maxHeight = max( $maxHeight, $this->grid[$i] );
    }

    // Loop through all found spots and rate them, based on their size and height
    // This way the smallest possible spot in the lowest possible height is filled
    $targetHeight = max(max($this->grid), 0);
    $targetLeft = 0;

    $bestScore = -1;
    foreach( $freeSpots as $fs ) {

      // Difference of the height of this spot to the total height of the grid
      $heightScore = ( $maxHeight - $fs['height'] );

      // Relation of the required and the available space
      $widthScore = $boxWidth / $fs['width'];

      // The score for this spot is calculated by these both criteria
      $score = $heightScore * $heightScore + $widthScore * 2;

      // Is the score for this spot higher than for the last one we found?
      if( $fs['width'] >= $boxWidth && $score > $bestScore ) {
        $targetHeight = $fs['height'];
        $targetLeft = $fs['left'];
        $bestScore = $score;
      }
    }

    $newHeight = $targetHeight + $boxHeight;

    // Adjust grid height
    for( $j = 0; $j < $boxWidth; $j++ ) {
      $this->grid[$targetLeft + $j] = $newHeight;
    }

    return array( $targetLeft, $targetHeight );
  }
}

?>
