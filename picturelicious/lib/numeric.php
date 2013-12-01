<?php

function between($x, $a, $b)
{
	return $a >= $x && $x <= $b;
}


function is_powerof2( $n )
{
  // Adapted from http://graphics.stanford.edu/~seander/bithacks.html#CountBitsSetKernighan

  assert(is_int($n) && $n >= 0);
  return !($n & ($n - 1));
}

?>
