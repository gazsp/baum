<?php

class CategoryHierarchyTest extends CategoryTestCase
{
    public function testAllStatic()
    {
        $results = Category::all();
        $expected = Category::query()->orderBy('lft')->get();

        $this->assertEquals($results, $expected);
    }

    public function testAllStaticSomeColumns()
    {
        $results = Category::all(['id', 'name'])->toArray();
        $expected = Category::query()->select(['id', 'name'])->orderBy('lft')->get()->toArray();

        $this->assertEquals($results, $expected);
    }

    public function testAllStaticWithCustomOrder()
    {
        $results = OrderedCategory::all();
        $expected = OrderedCategory::query()->orderBy('name')->get();

        $this->assertEquals($results, $expected);
    }

    public function testRootsStatic()
    {
        $query = Category::whereNull('parent_id')->get();

        $roots = Category::roots()->get();

        $this->assertEquals($query->count(), $roots->count());
        $this->assertCount(2, $roots);

        foreach ($query->lists('id') as $node) {
            $this->assertContains($node, $roots->lists('id'));
        }
    }

    public function testRootsStaticWithCustomOrder()
    {
        $category = OrderedCategory::create(['name' => 'A new root is born']);
        $category->syncOriginal(); // ¿? --> This should be done already !?

    $roots = OrderedCategory::roots()->get();

        $this->assertCount(3, $roots);
        $this->assertEquals($category->getAttributes(), $roots->first()->getAttributes());
    }

    public function testRootStatic()
    {
        $this->assertEquals(Category::root(), $this->categories('Root 1'));
    }

    public function testAllLeavesStatic()
    {
        $allLeaves = Category::allLeaves()->get();

        $this->assertCount(4, $allLeaves);

        $leaves = $allLeaves->lists('name');

        $this->assertContains('Child 1', $leaves);
        $this->assertContains('Child 2.1', $leaves);
        $this->assertContains('Child 3', $leaves);
        $this->assertContains('Root 2', $leaves);
    }

    public function testAllTrunksStatic()
    {
        $allTrunks = Category::allTrunks()->get();

        $this->assertCount(1, $allTrunks);

        $trunks = $allTrunks->lists('name');
        $this->assertContains('Child 2', $trunks);
    }

    public function testGetRoot()
    {
        $this->assertEquals($this->categories('Root 1'), $this->categories('Root 1')->getRoot());
        $this->assertEquals($this->categories('Root 2'), $this->categories('Root 2')->getRoot());

        $this->assertEquals($this->categories('Root 1'), $this->categories('Child 1')->getRoot());
        $this->assertEquals($this->categories('Root 1'), $this->categories('Child 2')->getRoot());
        $this->assertEquals($this->categories('Root 1'), $this->categories('Child 2.1')->getRoot());
        $this->assertEquals($this->categories('Root 1'), $this->categories('Child 3')->getRoot());
    }

    public function testGetRootEqualsSelfIfUnpersisted()
    {
        $category = new Category;

        $this->assertEquals($category->getRoot(), $category);
    }

    public function testGetRootEqualsValueIfSetIfUnpersisted()
    {
        $parent = Category::roots()->first();

        $child = new Category;
        $child->setAttribute($child->getParentColumnName(), $parent->getKey());

        $this->assertEquals($child->getRoot(), $parent);
    }

    public function testIsRoot()
    {
        $this->assertTrue($this->categories('Root 1')->isRoot());
        $this->assertTrue($this->categories('Root 2')->isRoot());

        $this->assertFalse($this->categories('Child 1')->isRoot());
        $this->assertFalse($this->categories('Child 2')->isRoot());
        $this->assertFalse($this->categories('Child 2.1')->isRoot());
        $this->assertFalse($this->categories('Child 3')->isRoot());
    }

    public function testGetLeaves()
    {
        $leaves = [$this->categories('Child 1'), $this->categories('Child 2.1'), $this->categories('Child 3')];

        $this->assertEquals($leaves, $this->categories('Root 1')->getLeaves()->all());
    }

    public function testGetLeavesInIteration()
    {
        $node = $this->categories('Root 1');

        $expectedIds = [2, 4, 5];

        foreach ($node->getLeaves() as $i => $leaf) {
            $this->assertEquals($expectedIds[$i], $leaf->getKey());
        }
    }

    public function testGetTrunks()
    {
        $trunks = [$this->categories('Child 2')];

        $this->assertEquals($trunks, $this->categories('Root 1')->getTrunks()->all());
    }

    public function testGetTrunksInIteration()
    {
        $node = $this->categories('Root 1');

        $expectedIds = [3];

        foreach ($node->getTrunks() as $i => $trunk) {
            $this->assertEquals($expectedIds[$i], $trunk->getKey());
        }
    }

    public function testIsLeaf()
    {
        $this->assertTrue($this->categories('Child 1')->isLeaf());
        $this->assertTrue($this->categories('Child 2.1')->isLeaf());
        $this->assertTrue($this->categories('Child 3')->isLeaf());
        $this->assertTrue($this->categories('Root 2')->isLeaf());

        $this->assertFalse($this->categories('Root 1')->isLeaf());
        $this->assertFalse($this->categories('Child 2')->isLeaf());

        $new = new Category;
        $this->assertFalse($new->isLeaf());
    }

    public function testIsTrunk()
    {
        $this->assertFalse($this->categories('Child 1')->isTrunk());
        $this->assertFalse($this->categories('Child 2.1')->isTrunk());
        $this->assertFalse($this->categories('Child 3')->isTrunk());
        $this->assertFalse($this->categories('Root 2')->isTrunk());

        $this->assertFalse($this->categories('Root 1')->isTrunk());
        $this->assertTrue($this->categories('Child 2')->isTrunk());

        $new = new Category;
        $this->assertFalse($new->isTrunk());
    }

    public function testWithoutNodeScope()
    {
        $child = $this->categories('Child 2.1');

        $expected = [$this->categories('Root 1'), $child];

        $this->assertEquals($expected, $child->ancestorsAndSelf()->withoutNode($this->categories('Child 2'))->get()->all());
    }

    public function testWithoutSelfScope()
    {
        $child = $this->categories('Child 2.1');

        $expected = [$this->categories('Root 1'), $this->categories('Child 2')];

        $this->assertEquals($expected, $child->ancestorsAndSelf()->withoutSelf()->get()->all());
    }

    public function testWithoutRootScope()
    {
        $child = $this->categories('Child 2.1');

        $expected = [$this->categories('Child 2'), $child];

        $this->assertEquals($expected, $child->ancestorsAndSelf()->withoutRoot()->get()->all());
    }

    public function testLimitDepthScope()
    {
        with(new CategorySeeder)->nestUptoAt($this->categories('Child 2.1'), 10);

        $node = $this->categories('Child 2');

        $descendancy = $node->descendants()->lists('id')->toArray();

        $this->assertEmpty($node->descendants()->limitDepth(0)->lists('id'));
        $this->assertEquals($node, $node->descendantsAndSelf()->limitDepth(0)->first());

        $this->assertEquals(array_slice($descendancy, 0, 3), $node->descendants()->limitDepth(3)->lists('id')->toArray());
        $this->assertEquals(array_slice($descendancy, 0, 5), $node->descendants()->limitDepth(5)->lists('id')->toArray());
        $this->assertEquals(array_slice($descendancy, 0, 7), $node->descendants()->limitDepth(7)->lists('id')->toArray());

        $this->assertEquals($descendancy, $node->descendants()->limitDepth(1000)->lists('id')->toArray());
    }

    public function testGetAncestorsAndSelf()
    {
        $child = $this->categories('Child 2.1');

        $expected = [$this->categories('Root 1'), $this->categories('Child 2'), $child];

        $this->assertEquals($expected, $child->getAncestorsAndSelf()->all());
    }

    public function testGetAncestorsAndSelfWithoutRoot()
    {
        $child = $this->categories('Child 2.1');

        $expected = [$this->categories('Child 2'), $child];

        $this->assertEquals($expected, $child->getAncestorsAndSelfWithoutRoot()->all());
    }

    public function testGetAncestors()
    {
        $child = $this->categories('Child 2.1');

        $expected = [$this->categories('Root 1'), $this->categories('Child 2')];

        $this->assertEquals($expected, $child->getAncestors()->all());
    }

    public function testGetAncestorsWithoutRoot()
    {
        $child = $this->categories('Child 2.1');

        $expected = [$this->categories('Child 2')];

        $this->assertEquals($expected, $child->getAncestorsWithoutRoot()->all());
    }

    public function testGetDescendantsAndSelf()
    {
        $parent = $this->categories('Root 1');

        $expected = [
      $parent,
      $this->categories('Child 1'),
      $this->categories('Child 2'),
      $this->categories('Child 2.1'),
      $this->categories('Child 3'),
    ];

        $this->assertCount(count($expected), $parent->getDescendantsAndSelf());

        $this->assertEquals($expected, $parent->getDescendantsAndSelf()->all());
    }

    public function testGetDescendantsAndSelfWithLimit()
    {
        with(new CategorySeeder)->nestUptoAt($this->categories('Child 2.1'), 3);

        $parent = $this->categories('Root 1');

        $this->assertEquals([$parent], $parent->getDescendantsAndSelf(0)->all());

        $this->assertEquals([
      $parent,
      $this->categories('Child 1'),
      $this->categories('Child 2'),
      $this->categories('Child 3'),
    ], $parent->getDescendantsAndSelf(1)->all());

        $this->assertEquals([
      $parent,
      $this->categories('Child 1'),
      $this->categories('Child 2'),
      $this->categories('Child 2.1'),
      $this->categories('Child 3'),
    ], $parent->getDescendantsAndSelf(2)->all());

        $this->assertEquals([
      $parent,
      $this->categories('Child 1'),
      $this->categories('Child 2'),
      $this->categories('Child 2.1'),
      $this->categories('Child 2.1.1'),
      $this->categories('Child 3'),
    ], $parent->getDescendantsAndSelf(3)->all());

        $this->assertEquals([
      $parent,
      $this->categories('Child 1'),
      $this->categories('Child 2'),
      $this->categories('Child 2.1'),
      $this->categories('Child 2.1.1'),
      $this->categories('Child 2.1.1.1'),
      $this->categories('Child 3'),
    ], $parent->getDescendantsAndSelf(4)->all());

        $this->assertEquals([
      $parent,
      $this->categories('Child 1'),
      $this->categories('Child 2'),
      $this->categories('Child 2.1'),
      $this->categories('Child 2.1.1'),
      $this->categories('Child 2.1.1.1'),
      $this->categories('Child 2.1.1.1.1'),
      $this->categories('Child 3'),
    ], $parent->getDescendantsAndSelf(10)->all());
    }

    public function testGetDescendants()
    {
        $parent = $this->categories('Root 1');

        $expected = [
      $this->categories('Child 1'),
      $this->categories('Child 2'),
      $this->categories('Child 2.1'),
      $this->categories('Child 3'),
    ];

        $this->assertCount(count($expected), $parent->getDescendants());

        $this->assertEquals($expected, $parent->getDescendants()->all());
    }

    public function testGetDescendantsWithLimit()
    {
        with(new CategorySeeder)->nestUptoAt($this->categories('Child 2.1'), 3);

        $parent = $this->categories('Root 1');

        $this->assertEmpty($parent->getDescendants(0)->all());

        $this->assertEquals([
      $this->categories('Child 1'),
      $this->categories('Child 2'),
      $this->categories('Child 3'),
    ], $parent->getDescendants(1)->all());

        $this->assertEquals([
      $this->categories('Child 1'),
      $this->categories('Child 2'),
      $this->categories('Child 2.1'),
      $this->categories('Child 3'),
    ], $parent->getDescendants(2)->all());

        $this->assertEquals([
      $this->categories('Child 1'),
      $this->categories('Child 2'),
      $this->categories('Child 2.1'),
      $this->categories('Child 2.1.1'),
      $this->categories('Child 3'),
    ], $parent->getDescendants(3)->all());

        $this->assertEquals([
      $this->categories('Child 1'),
      $this->categories('Child 2'),
      $this->categories('Child 2.1'),
      $this->categories('Child 2.1.1'),
      $this->categories('Child 2.1.1.1'),
      $this->categories('Child 3'),
    ], $parent->getDescendants(4)->all());

        $this->assertEquals([
      $this->categories('Child 1'),
      $this->categories('Child 2'),
      $this->categories('Child 2.1'),
      $this->categories('Child 2.1.1'),
      $this->categories('Child 2.1.1.1'),
      $this->categories('Child 2.1.1.1.1'),
      $this->categories('Child 3'),
    ], $parent->getDescendants(5)->all());

        $this->assertEquals([
      $this->categories('Child 1'),
      $this->categories('Child 2'),
      $this->categories('Child 2.1'),
      $this->categories('Child 2.1.1'),
      $this->categories('Child 2.1.1.1'),
      $this->categories('Child 2.1.1.1.1'),
      $this->categories('Child 3'),
    ], $parent->getDescendants(10)->all());
    }

    public function testDescendantsRecursesChildren()
    {
        $a = Category::create(['name' => 'A']);
        $b = Category::create(['name' => 'B']);
        $c = Category::create(['name' => 'C']);

    // a > b > c
    $b->makeChildOf($a);
        $c->makeChildOf($b);

        $a->reload();
        $b->reload();
        $c->reload();

        $this->assertEquals(1, $a->children()->count());
        $this->assertEquals(1, $b->children()->count());
        $this->assertEquals(2, $a->descendants()->count());
    }

    public function testGetImmediateDescendants()
    {
        $expected = [$this->categories('Child 1'), $this->categories('Child 2'), $this->categories('Child 3')];

        $this->assertEquals($expected, $this->categories('Root 1')->getImmediateDescendants()->all());

        $this->assertEquals([$this->categories('Child 2.1')], $this->categories('Child 2')->getImmediateDescendants()->all());

        $this->assertEmpty($this->categories('Root 2')->getImmediateDescendants()->all());
    }

    public function testIsSelfOrAncestorOf()
    {
        $this->assertTrue($this->categories('Root 1')->isSelfOrAncestorOf($this->categories('Child 1')));
        $this->assertTrue($this->categories('Root 1')->isSelfOrAncestorOf($this->categories('Child 2.1')));
        $this->assertTrue($this->categories('Child 2')->isSelfOrAncestorOf($this->categories('Child 2.1')));
        $this->assertFalse($this->categories('Child 2.1')->isSelfOrAncestorOf($this->categories('Child 2')));
        $this->assertFalse($this->categories('Child 1')->isSelfOrAncestorOf($this->categories('Child 2')));
        $this->assertTrue($this->categories('Child 1')->isSelfOrAncestorOf($this->categories('Child 1')));
    }

    public function testIsAncestorOf()
    {
        $this->assertTrue($this->categories('Root 1')->isAncestorOf($this->categories('Child 1')));
        $this->assertTrue($this->categories('Root 1')->isAncestorOf($this->categories('Child 2.1')));
        $this->assertTrue($this->categories('Child 2')->isAncestorOf($this->categories('Child 2.1')));
        $this->assertFalse($this->categories('Child 2.1')->isAncestorOf($this->categories('Child 2')));
        $this->assertFalse($this->categories('Child 1')->isAncestorOf($this->categories('Child 2')));
        $this->assertFalse($this->categories('Child 1')->isAncestorOf($this->categories('Child 1')));
    }

    public function testIsSelfOrDescendantOf()
    {
        $this->assertTrue($this->categories('Child 1')->isSelfOrDescendantOf($this->categories('Root 1')));
        $this->assertTrue($this->categories('Child 2.1')->isSelfOrDescendantOf($this->categories('Root 1')));
        $this->assertTrue($this->categories('Child 2.1')->isSelfOrDescendantOf($this->categories('Child 2')));
        $this->assertFalse($this->categories('Child 2')->isSelfOrDescendantOf($this->categories('Child 2.1')));
        $this->assertFalse($this->categories('Child 2')->isSelfOrDescendantOf($this->categories('Child 1')));
        $this->assertTrue($this->categories('Child 1')->isSelfOrDescendantOf($this->categories('Child 1')));
    }

    public function testIsDescendantOf()
    {
        $this->assertTrue($this->categories('Child 1')->isDescendantOf($this->categories('Root 1')));
        $this->assertTrue($this->categories('Child 2.1')->isDescendantOf($this->categories('Root 1')));
        $this->assertTrue($this->categories('Child 2.1')->isDescendantOf($this->categories('Child 2')));
        $this->assertFalse($this->categories('Child 2')->isDescendantOf($this->categories('Child 2.1')));
        $this->assertFalse($this->categories('Child 2')->isDescendantOf($this->categories('Child 1')));
        $this->assertFalse($this->categories('Child 1')->isDescendantOf($this->categories('Child 1')));
    }

    public function testGetSiblingsAndSelf()
    {
        $child = $this->categories('Child 2');

        $expected = [$this->categories('Child 1'), $child, $this->categories('Child 3')];
        $this->assertEquals($expected, $child->getSiblingsAndSelf()->all());

        $expected = [$this->categories('Root 1'), $this->categories('Root 2')];
        $this->assertEquals($expected, $this->categories('Root 1')->getSiblingsAndSelf()->all());
    }

    public function testGetSiblings()
    {
        $child = $this->categories('Child 2');

        $expected = [$this->categories('Child 1'), $this->categories('Child 3')];

        $this->assertEquals($expected, $child->getSiblings()->all());
    }

    public function testGetLeftSibling()
    {
        $this->assertEquals($this->categories('Child 1'), $this->categories('Child 2')->getLeftSibling());
        $this->assertEquals($this->categories('Child 2'), $this->categories('Child 3')->getLeftSibling());
    }

    public function testGetLeftSiblingOfFirstRootIsNull()
    {
        $this->assertNull($this->categories('Root 1')->getLeftSibling());
    }

    public function testGetLeftSiblingWithNoneIsNull()
    {
        $this->assertNull($this->categories('Child 2.1')->getLeftSibling());
    }

    public function testGetLeftSiblingOfLeftmostNodeIsNull()
    {
        $this->assertNull($this->categories('Child 1')->getLeftSibling());
    }

    public function testGetRightSibling()
    {
        $this->assertEquals($this->categories('Child 3'), $this->categories('Child 2')->getRightSibling());
        $this->assertEquals($this->categories('Child 2'), $this->categories('Child 1')->getRightSibling());
    }

    public function testGetRightSiblingOfRoots()
    {
        $this->assertEquals($this->categories('Root 2'), $this->categories('Root 1')->getRightSibling());
        $this->assertNull($this->categories('Root 2')->getRightSibling());
    }

    public function testGetRightSiblingWithNoneIsNull()
    {
        $this->assertNull($this->categories('Child 2.1')->getRightSibling());
    }

    public function testGetRightSiblingOfRightmostNodeIsNull()
    {
        $this->assertNull($this->categories('Child 3')->getRightSibling());
    }

    public function testInsideSubtree()
    {
        $this->assertFalse($this->categories('Child 1')->insideSubtree($this->categories('Root 2')));
        $this->assertFalse($this->categories('Child 2')->insideSubtree($this->categories('Root 2')));
        $this->assertFalse($this->categories('Child 3')->insideSubtree($this->categories('Root 2')));

        $this->assertTrue($this->categories('Child 1')->insideSubtree($this->categories('Root 1')));
        $this->assertTrue($this->categories('Child 2')->insideSubtree($this->categories('Root 1')));
        $this->assertTrue($this->categories('Child 2.1')->insideSubtree($this->categories('Root 1')));
        $this->assertTrue($this->categories('Child 3')->insideSubtree($this->categories('Root 1')));

        $this->assertTrue($this->categories('Child 2.1')->insideSubtree($this->categories('Child 2')));
        $this->assertFalse($this->categories('Child 2.1')->insideSubtree($this->categories('Root 2')));
    }

    public function testGetLevel()
    {
        $this->assertEquals(0, $this->categories('Root 1')->getLevel());
        $this->assertEquals(1, $this->categories('Child 1')->getLevel());
        $this->assertEquals(2, $this->categories('Child 2.1')->getLevel());
    }

    public function testToHierarchyReturnsAnEloquentCollection()
    {
        $categories = Category::all()->toHierarchy();

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $categories);
    }

    public function testToHierarchyReturnsHierarchicalData()
    {
        $categories = Category::all()->toHierarchy();

        $this->assertEquals(2, $categories->count());

        $first = $categories->first();
        $this->assertEquals('Root 1', $first->name);
        $this->assertEquals(3, $first->children->count());

        $first_lvl2 = $first->children->first();
        $this->assertEquals('Child 1', $first_lvl2->name);
        $this->assertEquals(0, $first_lvl2->children->count());
    }

    public function testToHierarchyNestsCorrectly()
    {
        // Prune all categories
    Category::query()->delete();

    // Build a sample tree structure:
    //
    //   - A
    //     |- A.1
    //     |- A.2
    //   - B
    //     |- B.1
    //     |- B.2
    //         |- B.2.1
    //         |- B.2.2
    //           |- B.2.2.1
    //         |- B.2.3
    //     |- B.3
    //   - C
    //     |- C.1
    //     |- C.2
    //   - D
    //
    $a = Category::create(['name' => 'A']);
        $b = Category::create(['name' => 'B']);
        $c = Category::create(['name' => 'C']);
        $d = Category::create(['name' => 'D']);

        $ch = Category::create(['name' => 'A.1']);
        $ch->makeChildOf($a);

        $ch = Category::create(['name' => 'A.2']);
        $ch->makeChildOf($a);

        $ch = Category::create(['name' => 'B.1']);
        $ch->makeChildOf($b);

        $ch = Category::create(['name' => 'B.2']);
        $ch->makeChildOf($b);

        $ch2 = Category::create(['name' => 'B.2.1']);
        $ch2->makeChildOf($ch);

        $ch2 = Category::create(['name' => 'B.2.2']);
        $ch2->makeChildOf($ch);

        $ch3 = Category::create(['name' => 'B.2.2.1']);
        $ch3->makeChildOf($ch2);

        $ch2 = Category::create(['name' => 'B.2.3']);
        $ch2->makeChildOf($ch);

        $ch = Category::create(['name' => 'B.3']);
        $ch->makeChildOf($b);

        $ch = Category::create(['name' => 'C.1']);
        $ch->makeChildOf($c);

        $ch = Category::create(['name' => 'C.2']);
        $ch->makeChildOf($c);

        $this->assertTrue(Category::isValidNestedSet());

    // Build expectations (expected trees/subtrees)
    $expectedWholeTree = [
      'A' =>   ['A.1' => null, 'A.2' => null],
      'B' =>   [
        'B.1' => null,
        'B.2' =>  [
          'B.2.1' => null,
          'B.2.2' =>   ['B.2.2.1' => null],
          'B.2.3' => null,
        ],
        'B.3' => null,
      ],
      'C' =>   ['C.1' => null, 'C.2' => null],
      'D' => null,
    ];

        $expectedSubtreeA = ['A' =>   ['A.1' => null, 'A.2' => null]];

        $expectedSubtreeB = [
      'B' =>  [
        'B.1' => null,
        'B.2' =>  [
          'B.2.1' => null,
          'B.2.2' =>  ['B.2.2.1' => null],
          'B.2.3' => null,
        ],
        'B.3' => null,
      ],
    ];

        $expectedSubtreeC = ['C.1' => null, 'C.2' => null];

        $expectedSubtreeD = ['D' => null];

    // Perform assertions
    $wholeTree = hmap(Category::all()->toHierarchy()->toArray());
        $this->assertArraysAreEqual($expectedWholeTree, $wholeTree);

        $subtreeA = hmap($this->categories('A')->getDescendantsAndSelf()->toHierarchy()->toArray());
        $this->assertArraysAreEqual($expectedSubtreeA, $subtreeA);

        $subtreeB = hmap($this->categories('B')->getDescendantsAndSelf()->toHierarchy()->toArray());
        $this->assertArraysAreEqual($expectedSubtreeB, $subtreeB);

        $subtreeC = hmap($this->categories('C')->getDescendants()->toHierarchy()->toArray());
        $this->assertArraysAreEqual($expectedSubtreeC, $subtreeC);

        $subtreeD = hmap($this->categories('D')->getDescendantsAndSelf()->toHierarchy()->toArray());
        $this->assertArraysAreEqual($expectedSubtreeD, $subtreeD);

        $this->assertTrue($this->categories('D')->getDescendants()->toHierarchy()->isEmpty());
    }

    public function testToHierarchyNestsCorrectlyNotSequential()
    {
        $parent = $this->categories('Child 1');

        $parent->children()->create(['name' => 'Child 1.1']);

        $parent->children()->create(['name' => 'Child 1.2']);

        $this->assertTrue(Category::isValidNestedSet());

        $expected = [
      'Child 1' => [
        'Child 1.1' => null,
        'Child 1.2' => null,
      ],
    ];

        $parent->reload();
        $this->assertArraysAreEqual($expected, hmap($parent->getDescendantsAndSelf()->toHierarchy()->toArray()));
    }

    public function testToHierarchyNestsCorrectlyWithOrder()
    {
        with(new OrderedCategorySeeder)->run();

        $expectedWhole = [
      'Root A' => null,
      'Root Z' => [
        'Child A' => null,
        'Child C' => null,
        'Child G' => ['Child G.1' => null],
      ],
    ];

        $this->assertArraysAreEqual($expectedWhole, hmap(OrderedCategory::all()->toHierarchy()->toArray()));

        $expectedSubtreeZ = [
      'Root Z' => [
        'Child A' => null,
        'Child C' => null,
        'Child G' => ['Child G.1' => null],
      ],
    ];
        $this->assertArraysAreEqual($expectedSubtreeZ, hmap($this->categories('Root Z', 'OrderedCategory')->getDescendantsAndSelf()->toHierarchy()->toArray()));
    }

    public function testGetNestedList()
    {
        $seperator = ' ';
        $nestedList = Category::getNestedList('name', 'id', $seperator);

        $expected = [
          1 => str_repeat($seperator, 0).'Root 1',
          2 => str_repeat($seperator, 1).'Child 1',
          3 => str_repeat($seperator, 1).'Child 2',
          4 => str_repeat($seperator, 2).'Child 2.1',
          5 => str_repeat($seperator, 1).'Child 3',
          6 => str_repeat($seperator, 0).'Root 2',
        ];

        $this->assertArraysAreEqual($expected, $nestedList);
    }

    public function testGetNestedListWithParent() {
        $separator = ' ';
        $parent = Category::find(2);
        $nestedList = Category::getNestedList('name', 'id', $separator, $parent);

        $expected = array(
            2 => str_repeat($separator, 0). 'Child 1',
        );
        $this->assertArraysAreEqual($expected, $nestedList);

        $nestedList = Category::getNestedList('name', 'id', $separator, $parent, false);

        $expected = array(
            2 => str_repeat($separator, 1). 'Child 1',
        );

        $this->assertArraysAreEqual($expected, $nestedList);
    }

}
