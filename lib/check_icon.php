<?php 

function renderCheck (){

	$svg = "
		<svg xmlns='http://www.w3.org/2000/svg' version='1.1' viewBox='0 0 50 50'>
			<rect x='0' y='0' width='50' height='50' stroke='#000' stroke-width='10' fill='#fff' rx='10' /> 
			<line x1='40' y1='10' x2='20' y2='40' class='check' stroke='#fff' stroke-width='10' stroke-linecap='round' />
			<line x1='20' y1='40' x2='10' y2='30' class='check' stroke='#fff' stroke-width='10' stroke-linecap='round' />
		</svg>
		";
	return $svg;
}