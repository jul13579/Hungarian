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
    public $matrix;

    /**
     * The reduced cost matrix
     * 
     * @var Matrix
     */
    protected $reduced;

    /**
     * The starred zeros of the matrix
     */
    protected $starred = [];

    /**
     * The primed zeros of the matrix
     */
    protected $primed = [];

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
        if (!$this->matrix->isSquare()) {
            throw new \Exception("The matrix has to be square. Consult https://www.wikihow.com/Use-the-Hungarian-Algorithm to learn about inserting dummy tasks/workers.");
        }
        $this->starred = array_fill(0, $this->matrix->getM(), -1);
        $this->primed = $this->starred;
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

    protected function isRowPrimed(int $row_index)
    {
        return $this->primed[$row_index] > -1;
    }

    protected function isColumnCovered(int $column_index)
    {
        return $this->starred[$column_index] > -1 && !$this->isRowPrimed($this->starred[$column_index]);
    }

    protected function getRowMinimums(Matrix $matrix)
    {
        return array_map("min", $matrix->getMatrix());
    }

    protected function getColumnMinimums(Matrix $matrix)
    {
        return $this->getRowMinimums($matrix->transpose());
    }

    protected function getUncoveredMinimums(Matrix $matrix)
    {
        return array_map(function (int $row_index, array $row) {
            return min(array_filter($row, function (int $element, int $column_index) use ($row_index) {
                return !$this->isRowPrimed($row_index) && !$this->isColumnCovered($column_index);
            }, ARRAY_FILTER_USE_BOTH));
        }, array_keys($matrix->getMatrix()), $matrix->getMatrix());
    }

    /**
     * Get the reduced matrix
     *
     * @return Matrix
     */
    public function getReducedMatrix()
    {
        if (!isset($this->reduced)) {
            $this->reduced = $this->reduce($this->matrix);
        }
        return $this->reduced;
    }

    /**
     * Calculate total cost of given worker-to-task-assignment
     *
     * @param array $assignment Assignment
     * @return int
     */
    public function totalCost(array $assignment)
    {
        return array_sum(array_map(function (int $column, int $row) {
            return $this->matrix[$row][$column];
        }, array_keys($assignment), $assignment));
    }

    /**
     * Reduces the cost matrix
     *
     * @param Matrix $matrix
     * @return Matrix
     */
    protected function reduce(Matrix $matrix)
    {
        $columnMinimums = $this->getColumnMinimums($matrix);
        $matrix = $matrix->transpose()->subtract(
            new Matrix(array_map(function (int $min) use ($matrix) {
                return new Vector(array_fill(0, $matrix->getM(), $min));
            }, $columnMinimums))
        )->transpose();

        $rowMinimums = $this->getRowMinimums($matrix);
        $matrix = $matrix->subtract(
            new Matrix(array_map(function (int $min) use ($matrix) {
                return new Vector(array_fill(0, $matrix->getM(), $min));
            }, $rowMinimums))
        );

        return $matrix;
    }

    /**
     * Tries to star as many zeros as possible, given the reduced matrix
     *
     * @var Matrix The reduced matrix
     * @var array The array to store coverage-information to
     * @return array
     */
    protected function starZeros(Matrix &$matrix)
    {
        $starred = [];
        foreach ($matrix->asVectors() as $column_index => $vector) {
            $rows = array_values(
                array_diff(
                    array_keys($vector->getVector(), 0, true),
                    $starred
                )
            );
            if (isset($rows[0])) {
                $starred[$column_index] = $rows[0];
            }
        }
        return $starred;
    }

    protected function getUncoveredMatrix(Matrix $matrix, array $covered_columns, array $covered_rows)
    {
        foreach (array_reverse($covered_columns) as $column) {
            $matrix = $matrix->columnExclude($column);
        }
        foreach (array_reverse($covered_rows) as $row) {
            $matrix = $matrix->rowExclude($row);
        }
        return $matrix;
    }

    // public function addPrime($row, $column)
    // {
    //     $this->primed[$row] = $column;
    //     return $this;
    // }

    // public function addStar($row, $column)
    // {
    //     $this->starred[$row] = $column;
    //     return $this;
    // }

    // public function getPrimed()
    // {
    //     return $this->primed;
    // }

    // public function hasPrimeInColumn($column)
    // {
    //     return (bool)array_search($column, $this->primed, true);
    // }

    // public function getPrimeFromColumn($column)
    // {
    //     return array_search($column, $this->primed, true);
    // }

    // public function hasPrimeInRow($row)
    // {
    //     return array_key_exists($row, $this->primed);
    // }

    // public function getPrimeFromRow($row)
    // {
    //     if (!key_exists($row, $this->primed)) {
    //         return false;
    //     }
    //     return $this->primed[$row];
    // }

    // public function hasStarInColumn($column)
    // {
    //     return array_search($column, $this->starred, true) !== false;
    // }

    // public function getStarFromColumn($column)
    // {
    //     return array_search($column, $this->starred, true);
    // }

    // public function hasStarInRow($row)
    // {
    //     return array_key_exists($row, $this->starred);
    // }

    // public function getStarFromRow($row)
    // {
    //     if (!key_exists($row, $this->starred)) {
    //         return false;
    //     }
    //     return $this->starred[$row];
    // }

    // public function getZeroMatrix()
    // {
    //     $zeros = [];
    //     foreach ($this->reduced as $row => $cells) {
    //         $zeros[$row] = array_keys($cells, 0, true);
    //     }
    //     return $zeros;
    // }

    // public function getCoveredZeroMatrix($zero_matrix)
    // {
    //     $covered_zero_matrix = [];
    //     foreach ($zero_matrix as $row => $cells) {
    //         foreach ($cells as $column) {
    //             if (in_array($row, $this->covered['row'], true) || in_array($column, $this->covered['column'], true)) {
    //                 $covered_zero_matrix[$row][] = $column;
    //             }
    //         }
    //     }
    //     return $covered_zero_matrix;
    // }

    // public function getNonCoveredZeroMatrix($zero_matrix)
    // {
    //     $non_covered_zero_matrix = [];
    //     foreach ($zero_matrix as $row => $cells) {
    //         foreach ($cells as $column) {
    //             if (!in_array($row, $this->covered['row'], true) && !in_array($column, $this->covered['column'], true)) {
    //                 $non_covered_zero_matrix[$row][] = $column;
    //             }
    //         }
    //     }
    //     return $non_covered_zero_matrix;
    // }

    /**
     * Solves the matrix using the hungarian algorithm
     *
     * @return array
     */
    public function solve()
    {
        /**
         * Step 1)
         * - Reduce matrix
         * - Try to star as much zeros as possible
         * - If all workers were assigned, return solution
         */
        $this->reduced = $this->reduce($this->matrix);
        $this->starred = $this->starZeros($this->reduced);

        if (count($this->starred) === $this->matrix->getM()) {
            return $this->starred;
        }

        /**
         * Step 2)
         * - Get the minimum value of uncovered elements
         * - Subtract minimum from double covered elements
         * - Add minimum to uncovered elements
         * - Prime any uncovered zero
         * - If there is a starred zero in the primed zero's row, uncover the starred zero's column
         */
        $uncovered_matrix = $this->getUncoveredMatrix($this->reduced, $this->covered['column'], $this->covered['row']);
        $min = min(min($uncovered_matrix->getMatrix()));

        $rows = $this->covered['row'];
        $columns = $this->covered['column'];
        $row_size = $this->reduced->getN();
        $sum_matrix = new Matrix(
            array_map(function ($row_index) use ($rows, $columns, $row_size, $min) {
                $new_row = array_fill(0, $row_size, 0);
                foreach ($new_row as $column_index => $element) {
                    if (in_array($row_index, $rows) && in_array($column_index, $columns)) {
                        $new_row[$column_index] = $min;
                    } elseif (!in_array($row_index, $rows) && !in_array($column_index, $columns)) {
                        $new_row[$column_index] = -$min;
                    }
                }
                return $new_row;
            }, range(0, $this->reduced->getM() - 1))
        );
        $this->reduced = $this->reduced->add($sum_matrix);

        for ($column_index = 0; $column_index < $this->reduced->getM(); $column_index++) {
            if (in_array($column_index, $this->covered['column'])) {
                continue;
            }
            for ($row_index = 0; $row_index < $this->reduced->getN(); $row_index++) {
                if (!in_array($row_index, $this->covered['row']) && $this->reduced[$row_index][$column_index] === 0) {
                    $this->primed[$column_index] = $row_index;
                    $column_to_uncover = array_search($row_index, $this->starred, true);
                    if (isset($column_to_uncover)) {
                        unset($this->covered['column'][$column_to_uncover]);
                    }
                    break 2;
                }
            }
        }


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