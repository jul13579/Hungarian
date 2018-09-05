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

    const COLUMN_REDUCTION = 0;
    const ROW_REDUCTION = 1;

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

    protected function isColumnStarred(int $column_index)
    {
        return $this->starred[$column_index] > -1;
    }

    protected function isRowStarred(int $row_index)
    {
        return isset(array_flip($this->starred)[$row_index]);
    }

    protected function isRowPrimed(int $row_index)
    {
        return $this->primed[$row_index] > -1;
    }

    protected function isColumnCovered(int $column_index)
    {
        return $this->isColumnStarred($column_index) && !$this->isRowPrimed($this->starred[$column_index]);
    }

    protected function isRowCovered(int $row_index)
    {
        return $this->isRowPrimed($row_index);
    }

    protected function getRowMinimums(Matrix $matrix)
    {
        return new Vector(array_map("min", $matrix->getMatrix()));
    }

    protected function getColumnMinimums(Matrix $matrix)
    {
        return $this->getRowMinimums($matrix->transpose());
    }

    protected function getUncoveredRowElementMinimums(Matrix $matrix)
    {
        return new Vector(array_map(function (int $row_index, array $row) {
            return min(array_filter($row, function (int $element, int $column_index) use ($row_index) {
                return !$this->isColumnCovered($column_index);// && !$this->isRowPrimed($row_index);
            }, ARRAY_FILTER_USE_BOTH));
        }, array_keys($matrix->getMatrix()), $matrix->getMatrix()));
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

    protected function reduce(Matrix $matrix, Vector $minimums, int $reduction_type)
    {
        $matrix = $reduction_type === self::COLUMN_REDUCTION ? $matrix->transpose() : $matrix;

        $matrix = $matrix->subtract(
            new Matrix(array_map(function (int $min) use ($matrix) {
                return new Vector(array_fill(0, $matrix->getM(), $min));
            }, $minimums->getVector()))
        );

        return $reduction_type === self::COLUMN_REDUCTION ? $matrix->transpose() : $matrix;
    }

    /**
     * Tries to star as many zeros as possible, given the reduced matrix
     *
     * @var Matrix The reduced matrix
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
        return array_replace($this->starred, $starred);
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
        $columnMinimums = $this->getColumnMinimums($this->matrix);
        $this->reduced = $this->reduce($this->matrix, $columnMinimums, self::COLUMN_REDUCTION);
        $rowMinimums = $this->getRowMinimums($this->reduced);
        $this->reduced = $this->reduce($this->reduced, $rowMinimums, self::ROW_REDUCTION);


        $this->starred = $this->starZeros($this->reduced);

        check_all_starred :
            if (min($this->starred) > -1) {
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
        subtract_minimum :
            $uncoveredRowMinimums = $this->getUncoveredRowElementMinimums($this->reduced);
            $min = min(array_filter($uncoveredRowMinimums->getVector(), function (int $element, int $row_index) {
                return !$this->isRowCovered($row_index);
            }, ARRAY_FILTER_USE_BOTH));
        if ($min > 0) {
            $columnMinimums = $columnMinimums->getVector();
            $rowMinimums = $rowMinimums->getVector();
            $uncoveredRowMinimums = $uncoveredRowMinimums->getVector();
            foreach (range(0, $this->matrix->getM() - 1) as $i) {
                if (!$this->isColumnCovered($i)) {
                    $columnMinimums[$i] += $min;
                }
                if ($this->isRowCovered($i)) {
                    $rowMinimums[$i] -= $min;
                } else {
                    $uncoveredRowMinimums[$i] -= $min;
                }
            }
            $columnMinimums = new Vector($columnMinimums);
            $rowMinimums = new Vector($rowMinimums);
            $uncoveredRowMinimums = new Vector($uncoveredRowMinimums);

            $this->reduced = $this->reduce($this->matrix, $columnMinimums, self::COLUMN_REDUCTION);
            $this->reduced = $this->reduce($this->reduced, $rowMinimums, self::ROW_REDUCTION);
        }

        prime_uncovered_zero :
            $chosen_zero = [
            "row" => -1,
            "column" => -1,
        ];
        foreach ($this->reduced->getMatrix() as $row_index => $row) {
            foreach ($row as $column_index => $cell) {
                if ($cell === 0 && !$this->isRowCovered($row_index) && !$this->isColumnCovered($column_index)) {
                    $chosen_zero["row"] = $row_index;
                    $chosen_zero["column"] = $column_index;
                    break 2;
                }
            }
        }
        $this->primed = array_replace($this->primed, array($chosen_zero["row"] => $chosen_zero["column"]));
        if ($this->isRowStarred($chosen_zero["row"])) {
            goto subtract_minimum;
        }

        star_primed_zero :
            if ($this->isColumnStarred($chosen_zero["column"])) {
            $starred_zero = [
                "row" => $this->starred[$chosen_zero["column"]],
                "column" => $chosen_zero["column"]
            ];
            $this->starred = array_replace($this->starred, array($chosen_zero["column"] => $chosen_zero["row"]));
            $chosen_zero = [
                "row" => $starred_zero["row"],
                "column" => $this->primed[$starred_zero["row"]]
            ];
            goto star_primed_zero;
        } else {
            $this->starred = array_replace($this->starred, array($chosen_zero["column"] => $chosen_zero["row"]));
        }

        delete_all_primes :
            $this->primed = array_fill(0, $this->matrix->getM(), -1);
        goto check_all_starred;

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