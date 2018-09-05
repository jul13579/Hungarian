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
    }
}