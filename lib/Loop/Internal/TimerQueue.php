<?php

namespace Amp\Loop\Internal;

use Amp\Loop\Watcher;

/**
 * Uses a binary tree stored in an array to implement a heap.
 */
final class TimerQueue
{
    /** @var object[] */
    private $data = [];

    /** @var int[] */
    private $pointers;

    /**
     * Inserts the watcher into the queue. Time complexity: O(log(n)).
     *
     * @param Watcher $watcher
     * @param int     $expiration
     *
     * @return void
     */
    public function insert(Watcher $watcher, int $expiration)
    {
        $entry = new class {
            public $watcher;
            public $expiration;
        };

        $entry->watcher = $watcher;
        $entry->expiration = $expiration;

        $node = \count($this->data);
        $this->data[$node] = $entry;
        $this->pointers[$watcher->id] = $node;

        while ($node !== 0 && $entry->expiration < $this->data[$parent = ($node - 1) >> 1]->expiration) {
            $temp = $this->data[$parent];
            $this->data[$node] = $temp;
            $this->pointers[$temp->watcher->id] = $node;

            $this->data[$parent] = $entry;
            $this->pointers[$watcher->id] = $parent;

            $node = $parent;
        }
    }

    /**
     * Removes the given watcher from the queue. Time complexity: O(1).
     *
     * @param Watcher $watcher
     *
     * @return void
     */
    public function remove(Watcher $watcher)
    {
        $id = $watcher->id;

        if (!isset($this->pointers[$id])) {
            return;
        }

        $this->removeAndRebuild($this->pointers[$id]);
    }

    /**
     * Deletes and returns the Watcher on top of the heap if it has expired, otherwise null is returned.
     * Time complexity: O(log(n)).
     *
     * @param int $now Current loop time.
     *
     * @return Watcher|null Expired watcher at the top of the heap or null if the watcher has not expired.
     */
    public function extract(int $now)
    {
        if (empty($this->data)) {
            throw new \Error('No data left in the heap.');
        }

        $data = $this->data[0];

        if ($data->expiration > $now) {
            return null;
        }

        $this->removeAndRebuild(0);

        return $data->watcher;
    }

    /**
     * @param int $node Remove the given node and then rebuild the data array from that node downward.
     *
     * @return void
     */
    private function removeAndRebuild(int $node)
    {
        $length = \count($this->data) - 1;
        $id = $this->data[$node]->watcher->id;
        $left = $this->data[$node] = $this->data[$length];
        $this->pointers[$left->watcher->id] = $node;
        unset($this->data[$length], $this->pointers[$id]);

        while (($child = ($node << 1) + 1) < $length) {
            if ($this->data[$child]->expiration < $this->data[$node]->expiration
                && ($child + 1 >= $length || $this->data[$child]->expiration < $this->data[$child + 1]->expiration)
            ) {
                // Left child is less than parent and right child.
                $swap = $child;
            } elseif ($child + 1 < $length && $this->data[$child + 1]->expiration < $this->data[$node]->expiration) {
                // Right child is less than parent and left child.
                $swap = $child + 1;
            } else { // Left and right child are greater than parent.
                break;
            }

            $left = $this->data[$node];
            $right = $this->data[$swap];

            $this->data[$node] = $right;
            $this->pointers[$right->watcher->id] = $node;

            $this->data[$swap] = $left;
            $this->pointers[$left->watcher->id] = $swap;

            $node = $swap;
        }
    }

    /**
     * Returns the expiration time value at the top of the heap. Time complexity: O(1).
     *
     * @return int Expiration time of the watcher at the top of the heap.
     */
    public function peek(): int
    {
        if (empty($this->data)) {
            throw new \Error('No data in the heap.');
        }

        return $this->data[0]->expiration;
    }

    /**
     * Determines if the heap is empty.
     *
     * @return  bool
     */
    public function isEmpty(): bool
    {
        return empty($this->data);
    }
}
