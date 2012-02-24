<?php
/*========================================================
/* Implementation of Douglas-Peuker in PHP.
/* 
/* Anthony Cartmell
/* ajcartmell@fonant.com
/* 
/* This software is provided as-is, with no warranty.
/* Please use and modify freely for anything you like :)
/* Version 1.2 - 18 Aug 2009  Fixes problem with line of three points.
/*                            Thanks to Craig Stanton http://craig.stanton.net.nz
/* Version 1.1 - 17 Jan 2007  Fixes nasty bug!
/*========================================================*/

class PolylineReducer
{
    private $original_points = array();
    private $tolerance;
    private $tolerance_squared;

    public function __construct($geopoints_array)
    {
        foreach ($geopoints_array as $point)
        {
            $this->original_points[] = new Vector($point->latitude,$point->longitude);
        }
        /*----- Include first and last points -----*/
        $this->original_points[0]->include = true;
        $this->original_points[count($this->original_points)-1]->include = true;
    }

    /**
     * Returns a list of GeoPoints for the simplest polyline that leaves
     * no original point more than $tolerance away from it.
     *
     * @param float  $tolerance
     * @return Geopoint array
     */
    public function SimplerLine($tolerance = 0.5)
    {
        $this->tolerance = $tolerance;
        $this->tolerance_squared = $tolerance*$tolerance;
        $this->DouglasPeucker(0,count($this->original_points)-1);
        foreach ($this->original_points as $point)
        {
            if ($point->include)
            {
                $out[] = new GeoPoint($point->x,$point->y);
            }
        }
        return $out;
    }

    /**
     * Douglas-Peuker polyline simplification algorithm. First draws single line
     * from start to end. Then finds largest deviation from this straight line, and if
     * greater than tolerance, includes that point, splitting the original line into
     * two new lines. Repeats recursively for each new line created.
     *
     * @param int $start_vertex_index
     * @param int $end_vertex_index
     */
    private function DouglasPeucker($start_vertex_index, $end_vertex_index)
    {
        if ($end_vertex_index <= $start_vertex_index + 1) // there is nothing to simplify
        return;

        // Make line from start to end
        $line = new Line($this->original_points[$start_vertex_index],$this->original_points[$end_vertex_index]);

        // Find largest distance from intermediate points to this line
        $max_dist_to_line_squared = 0;
        for ($index = $start_vertex_index+1; $index < $end_vertex_index; $index++)
        {
            $dist_to_line_squared = $line->DistanceToPointSquared($this->original_points[$index]);
            if ($dist_to_line_squared>$max_dist_to_line_squared)
            {
                $max_dist_to_line_squared = $dist_to_line_squared;
                $max_dist_index = $index;
            }
        }

        // Check max distance with tolerance
        if ($max_dist_to_line_squared > $this->tolerance_squared)        // error is worse than the tolerance
        {
            // split the polyline at the farthest vertex from S
            $this->original_points[$max_dist_index]->include = true;
            // recursively simplify the two subpolylines
            $this->DouglasPeucker($start_vertex_index,$max_dist_index);
            $this->DouglasPeucker($max_dist_index,$end_vertex_index);
        }
        // else the approximation is OK, so ignore intermediate vertices
    }
    
}


class Vector
{
    public $x;
    public $y;
    public $include;

    public function __construct($x,$y)
    {
        $this->x = $x;
        $this->y = $y;
    }

    public function DotProduct(Vector $v)
    {
        $dot = ($this->x * $v->x + $this->y * $v->y);
        return $dot;
    }

    public function Magnitude()
    {
        return sqrt($this->x*$this->x + $this->y*$this->y);
    }

    public function UnitVector()
    {
        if ($this->Magnitude()==0) return new Vector(0,0);
        return new Vector($this->x/$this->Magnitude(),$this->y/$this->Magnitude());
    }
}

class Line 
{
    public $p1;
    public $p2;

    public function __construct(Vector $p1,Vector $p2)
    {
        $this->p1 = $p1;
        $this->p2 = $p2;
    }

    public function LengthSquared()
    {
        $dx = $this->p1->x - $this->p2->x;
        $dy = $this->p1->y - $this->p2->y;
        return $dx*$dx + $dy*$dy;
    }
    
    public function DistanceToPointSquared(Vector $point)
    {
        $v = new Vector($point->x - $this->p1->x, $point->y - $this->p1->y);
        $l = new Vector($this->p2->x - $this->p1->x, $this->p2->y - $this->p1->y);
        $dot = $v->DotProduct($l->UnitVector());
        if ($dot<=0) // Point nearest P1
        {
            $dl = new Line($this->p1,$point);
            return $dl->LengthSquared();
        }
        if (($dot*$dot)>=$this->LengthSquared())  // Point nearest P2
        {
            $dl = new Line($this->p2,$point);
            return $dl->LengthSquared();
        }
        else // Point within line
        {
            $v2 = new Line($this->p1,$point);
            $h = $v2->LengthSquared();
            return $h - $dot*$dot;
        }
    }
}
?>