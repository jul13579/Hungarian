<?php
namespace Hungarian;

use MathPHP\LinearAlgebra\Matrix;
use MathPHP\LinearAlgebra\Vector;

class Hungarian
{
    /**
     * The assignment cost matrix to be minimised
     *
     * @var Matrix
     */
    public $matrix = [];

    /**
     * The reduced cost matrix
     * 
     * @var Matrix
     */
    protected $reduced = [];

    /**
     * The primed zeros of the matrix
     */
    protected $primed = [];

    /**
     * The starred zeros of the matrix
     */
    protected $starred = [];

    /**
     * The covered lines of the matrix
     */
    protected $covered = [
        'column' => [],
        'row' => []
    ];

    /**
     * Class constructor, which takes the matrix as an array or an object of MathPHP\LinearAlgebra\Matrix
     *
     * @param mixed Matrix as array or object of MathPHP\LinearAlgebra\Matrix.
     * @return Hungarian
     */
    public function __construct($matrix)
    {
        // $this->isValid($matrix);
        $this->matrix = is_a($matrix, "MathPHP\LinearAlgebra\Matrix") ? $matrix : new Matrix($matrix);
        $this->reduced = clone $this->matrix;
        if ($this->matrix->isSquare()) {
            throw new \Exception("The matrix has to be square. Consult https://www.wikihow.com/Use-the-Hungarian-Algorithm to learn about inserting dummy tasks/workers.");
        }
    }

    // public function isValid(array $matrix)
    // {
    //     if (count($matrix) == false) {
    //         throw new \Exception('Number of rows in matrix returns false.');
    //     }
    //     foreach ($matrix as $key => $row) {
    //         if (count($row) !== count(array_intersect_key($row, ...$matrix))) {
    //             throw new \Exception(printf('Column keys of row %u do not correspond to the column keys found in the rest of the matrix.', $key));
    //         }
    //     }
    //     return true;
    // }

    /**
     * Reduces the cost matrix
     *
     * @param Matrix $matrix
     * @return Matrix
     */
    protected function reduce(Matrix $matrix)
    {
        /*
         * Runs twice to:
         * 1) reduce rows (transpose first, then reduce the resulting columns)
         * 2) reduce columns (transpose again, then recude the resulting columns)
         */
        foreach (range(0, 1) as $run) {
            $matrix = $matrix->transpose();
            $matrix = $matrix->subtract(
                new Matrix(array_map(function (Vector $vector) {
                    return new Vector(array_fill(0, $vector->getN(), min($vector->getVector())));
                }, $matrix->asVectors()))
            );
        }
        return $matrix;
    }

    /**
     * Creates boolean matrix which "stars" zeros from the reduced matrix
     *
     * @var Matrix
     * @return array
     */
    protected function star_zeros(Matrix &$matrix, array $starred = [])
    {
        
        
        // if (count($starred) === $matrix->getM()) {
        //     return $starred;
        // } else {
        //     foreach ($matrix->getMatrix() as $row => $cells) {
        //         if (in_array($row, array_keys($starred))) {
        //             continue;
        //         }
        //         foreach (array_keys($cells, 0, true) as $column) {
        //             if (in_array($column, $starred)) {
        //                 continue;
        //             }
        //             return $this->star_zeros($matrix, array_merge($starred, array($row => $column)));
        //         }
        //     }
        // }


        // foreach ($this->reduced as $row => $cells) {
        //     $columns = array_diff(array_keys($cells, 0, true), $this->covered['column']);
        //     if (isset($columns[0])) {
        //         $this->addStar($row, $columns[0]);
        //         $this->covered['column'][] = $columns[0];
        //     }
        // }
    }

    public function addPrime($row, $column)
    {
        $this->primed[$row] = $column;
        return $this;
    }

    public function addStar($row, $column)
    {
        $this->starred[$row] = $column;
        return $this;
    }

    public function getPrimed()
    {
        return $this->primed;
    }

    public function hasPrimeInColumn($column)
    {
        return (bool)array_search($column, $this->primed, true);
    }

    public function getPrimeFromColumn($column)
    {
        return array_search($column, $this->primed, true);
    }

    public function hasPrimeInRow($row)
    {
        return array_key_exists($row, $this->primed);
    }

    public function getPrimeFromRow($row)
    {
        if (!key_exists($row, $this->primed)) {
            return false;
        }
        return $this->primed[$row];
    }

    public function hasStarInColumn($column)
    {
        return array_search($column, $this->starred, true) !== false;
    }

    public function getStarFromColumn($column)
    {
        return array_search($column, $this->starred, true);
    }

    public function hasStarInRow($row)
    {
        return array_key_exists($row, $this->starred);
    }

    public function getStarFromRow($row)
    {
        if (!key_exists($row, $this->starred)) {
            return false;
        }
        return $this->starred[$row];
    }

    public function getZeroMatrix()
    {
        $zeros = [];
        foreach ($this->reduced as $row => $cells) {
            $zeros[$row] = array_keys($cells, 0, true);
        }
        return $zeros;
    }

    public function getCoveredZeroMatrix($zero_matrix)
    {
        $covered_zero_matrix = [];
        foreach ($zero_matrix as $row => $cells) {
            foreach ($cells as $column) {
                if (in_array($row, $this->covered['row'], true) || in_array($column, $this->covered['column'], true)) {
                    $covered_zero_matrix[$row][] = $column;
                }
            }
        }
        return $covered_zero_matrix;
    }

    public function getNonCoveredZeroMatrix($zero_matrix)
    {
        $non_covered_zero_matrix = [];
        foreach ($zero_matrix as $row => $cells) {
            foreach ($cells as $column) {
                if (!in_array($row, $this->covered['row'], true) && !in_array($column, $this->covered['column'], true)) {
                    $non_covered_zero_matrix[$row][] = $column;
                }
            }
        }
        return $non_covered_zero_matrix;
    }

    /**
     * Solves the matrix using the hungarian algorithm
     *
     * @return void
     */
    public function solve()
    {
        $this->reduced = $this->reduce($this->reduced);

        $this->starred = $this->star_zeros($this->reduced);

        /*
         * Generate zero matrix
         */
        start :
            $zero_matrix = $this->getZeroMatrix();
        $non_covered_zero_matrix = $this->getNonCoveredZeroMatrix($zero_matrix);
        while ($non_covered_zero_matrix) {

            /*
             * Step 1:
             *  -  Select first non-covered zero and prime this selected zero
             *  -  If has starred zero in row of selected zero
             *     - Uncover column of starred zero
             *     - Cover row of starred zero
             *     Else
             *     - Step 2
             */
            $row = key($non_covered_zero_matrix);
            $column = $non_covered_zero_matrix[$row][0];
            $this->addPrime($row, $column);
            if ($this->hasStarInRow($row)) {

                // get column from the starred zero in the row
                $column = $this->getStarFromRow($row);

                // uncover the column of the starred zero
                $key = array_search($column, $this->covered['column'], true);
                unset($this->covered['column'][$key]);

                // cover the row
                $this->covered['row'][] = $row;
            } else {

                /*
                 * Step 2:
                 *  -  Get the sequence of starred and primed zeros connecting to the initial primed zero
                 *     - Get the starred zero in the column of the primed zero
                 *     - Get the primed zero in the row of the starred zero
                 *  -  Unstar the starred zeros from the sequence
                 *  -  Star the primed zeros from the sequence
                 *  -  Empty the list with primed zeros
                 *  -  Empty the list with covered columns and covered rows
                 *  -  Cover the columns with a starred zero in it
                 */
                $starred = [];
                $primed = [];
                $primed[$row] = $column;
                $i = $row;
                while (true) {

                    if (!$this->hasStarInColumn($primed[$i])) {

                        // Unstar the starred zeros from the sequence
                        foreach ($starred as $row => $column) {
                            unset($this->starred[$row]);
                        }

                        // Star the primed zeros from the sequence
                        foreach ($primed as $row => $column) {
                            $this->addStar($row, $column);
                        }

                        // Empty the list with primed zeros
                        $this->primed = [];

                        // Empty the list with covered columns
                        $this->covered['column'] = [];

                        // Empty the list with covered columns
                        $this->covered['row'] = [];

                        // Cover the columns with a starred zero in it
                        foreach ($this->starred as $row => $column) {
                            $this->covered['column'][] = $column;
                        }
                        break 1;
                    }

                    $star_row = $this->getStarFromColumn($primed[$i]);
                    $star_column = $primed[$i];
                    $starred[$star_row] = $star_column;

                    if ($this->hasPrimeInRow($star_row)) {
                        $prime_row = $star_row;
                        $prime_column = $this->getPrimeFromRow($prime_row);
                        $primed[$prime_row] = $prime_column;
                    } else {
                        die;
                    }

                    $i = $prime_row;
                }
            }

            $print ? $this->printMatrix($this->reduced, 'Reduced cost matrix of non-covered zero iteration:') : null;

            $zero_matrix = $this->getZeroMatrix();
            $non_covered_zero_matrix = $this->getNonCoveredZeroMatrix($zero_matrix);
        }

        /*
         * Step 3:
         *  -  If the number of covered columns is equal to the number of rows/columns of the cost matrix
         *     - The currently starred zeros show the optimal solution
         *
         */
        if (count($this->covered['column']) + count($this->covered['row']) === count($this->reduced)) {
            return $this->starred;
        } else {
            $non_covered_reduced_matrix = [];
            $once_covered_reduced_matrix = [];
            $twice_covered_reduced_matrix = [];
            foreach ($this->reduced as $row => $cells) {
                foreach ($cells as $column => $cell) {
                    if (!in_array($row, $this->covered['row'], true) && !in_array($column, $this->covered['column'], true)) {
                        $non_covered_reduced_matrix[$row][$column] = $cell;
                    } elseif (in_array($row, $this->covered['row'], true) && in_array($column, $this->covered['column'], true)) {
                        $twice_covered_reduced_matrix[$row][$column] = $cell;
                    } else {
                        $once_covered_reduced_matrix[$row][$column] = $cell;
                    }
                }
            }

            $min = INF;
            foreach ($non_covered_reduced_matrix as $row => $cells) {
                foreach ($cells as $column => $cell) {
                    $min = ($cell < $min) ? $cell : $min;
                }
            }
            foreach ($non_covered_reduced_matrix as $row => $cells) {
                foreach ($cells as $column => $cell) {
                    $this->reduced[$row][$column] -= $min;
                }
            }
            foreach ($twice_covered_reduced_matrix as $row => $cells) {
                foreach ($cells as $column => $cell) {
                    $this->reduced[$row][$column] += $min;
                }
            }

            goto start;
        }

    }
}