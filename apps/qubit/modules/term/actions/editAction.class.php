<?php

/*
 * This file is part of the Access to Memory (AtoM) software.
 *
 * Access to Memory (AtoM) is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Access to Memory (AtoM) is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Access to Memory (AtoM).  If not, see <http://www.gnu.org/licenses/>.
 */

class TermEditAction extends DefaultEditAction
{
    // Arrays not allowed in class constants
    public static $NAMES = [
        'code',

        // This position is intentional because narrowTerms ->processField()
        // and name ->processField() depends on the taxonomy value
        'taxonomy',

        'name',
        'narrowTerms',
        'parent',

        // Needs to go before selfReciprocal field
        'converseTerm',

        'relatedTerms',
        'selfReciprocal',
        'useFor',
    ];

    protected $updatedLabel = false;

    public function execute($request)
    {
        parent::execute($request);

        if ($request->isMethod('post')) {
            $this->form->bind($request->getPostParameters());
            if ($this->form->isValid()) {
                $this->processForm();

                $this->resource->save();

                $this->redirect([$this->resource, 'module' => 'term']);
            }
        }
    }

    protected function earlyExecute()
    {
        $this->form->getValidatorSchema()->setOption('allow_extra_fields', true);

        $this->resource = new QubitTerm();
        $title = $this->context->i18n->__('Add new term');

        if (isset($this->getRoute()->resource)) {
            $this->resource = $this->getRoute()->resource;
            if (!$this->resource instanceof QubitTerm) {
                $this->forward404();
            }

            // Check that this isn't the root
            if (!isset($this->resource->parent)) {
                $this->forward404();
            }

            // Check authorization
            if (QubitTerm::isProtected($this->resource->id) || (!QubitAcl::check($this->resource, 'update') && !QubitAcl::check($this->resource, 'translate'))) {
                QubitAcl::forwardUnauthorized();
            }

            // Add optimistic lock
            $this->form->setDefault('serialNumber', $this->resource->serialNumber);
            $this->form->setValidator('serialNumber', new sfValidatorInteger());
            $this->form->setWidget('serialNumber', new sfWidgetFormInputHidden());

            if (1 > strlen($title = $this->resource->__toString())) {
                $title = $this->context->i18n->__('Untitled');
            }

            $title = $this->context->i18n->__('Edit %1%', ['%1%' => $title]);
        } else {
            // Check authorization
            if (isset($this->request->taxonomy)) {
                $params = $this->context->routing->parse(Qubit::pathInfo($this->request->taxonomy));
                $taxonomy = $params['_sf_route']->resource;

                $authorized = QubitAcl::check($taxonomy, 'createTerm');
            } else {
                $authorized = QubitAcl::check(QubitTerm::getRoot(), 'create');
            }

            if (!$authorized) {
                QubitAcl::forwardUnauthorized();
            }
        }

        $this->response->setTitle("{$title} - {$this->response->getTitle()}");

        $this->scopeNotesComponent = new ObjectNotesComponent($this->context, 'object', 'notes');
        $this->scopeNotesComponent->resource = $this->resource;
        $this->scopeNotesComponent->execute($this->request, $options = ['type' => 'termScopeNotes']);

        $this->sourceNotesComponent = new ObjectNotesComponent($this->context, 'object', 'notes');
        $this->sourceNotesComponent->resource = $this->resource;
        $this->sourceNotesComponent->execute($this->request, $options = ['type' => 'termSourceNotes']);

        $this->displayNotesComponent = new ObjectNotesComponent($this->context, 'object', 'notes');
        $this->displayNotesComponent->resource = $this->resource;
        $this->displayNotesComponent->execute($this->request, $options = ['type' => 'termDisplayNotes']);
    }

    protected function addField($name)
    {
        switch ($name) {
            case 'code':
                $this->form->setDefault('code', $this->resource->code);
                $this->form->setValidator('code', new sfValidatorString());
                $this->form->setWidget('code', new sfWidgetFormInput());

                break;

            case 'name':
                $this->form->setDefault('name', $this->resource->name);
                $this->form->setValidator('name', new sfValidatorString(['required' => true], ['required' => $this->context->i18n->__('This is a mandatory element.')]));
                $this->form->setWidget('name', new sfWidgetFormInput());

                break;

            case 'narrowTerms':
                $this->form->setValidator('narrowTerms', new sfValidatorPass());
                $this->form->setWidget('narrowTerms', new QubitWidgetFormInputMany(['defaults' => []]));

                break;

            case 'parent':
                $this->form->setDefault('parent', $this->context->routing->generate(null, [$this->resource->parent, 'module' => 'term']));
                $this->form->setValidator('parent', new sfValidatorString());

                $choices = [];
                if (isset($this->resource->parent)) {
                    $choices[$this->context->routing->generate(null, [$this->resource->parent, 'module' => 'term'])] = $this->resource->parent;
                }

                if (isset($this->request->parent)) {
                    $this->form->setDefault('parent', $this->request->parent);

                    $params = $this->context->routing->parse(Qubit::pathInfo($this->request->parent));
                    $this->parent = $params['_sf_route']->resource;
                    $choices[$this->request->parent] = $this->parent;
                }

                $this->form->setWidget('parent', new sfWidgetFormSelect(['choices' => $choices]));

                break;

            case 'converseTerm':
                $this->form->setValidator('converseTerm', new sfValidatorString());

                $choices = [];
                if (0 < count($converseTerms = QubitRelation::getBySubjectOrObjectId($this->resource->id, ['typeId' => QubitTerm::CONVERSE_TERM_ID]))) {
                    $this->converseTerm = $converseTerms[0]->getOpposedObject($this->resource);

                    if (isset($this->converseTerm) && $this->converseTerm->id != $this->resource->id) {
                        $this->form->setDefault('converseTerm', $this->context->routing->generate(null, [$this->converseTerm, 'module' => 'term']));
                        $choices[$this->context->routing->generate(null, [$this->converseTerm, 'module' => 'term'])] = $this->converseTerm;
                    }
                }

                $this->form->setWidget('converseTerm', new sfWidgetFormSelect(['choices' => $choices]));

                break;

            case 'relatedTerms':
                $value = $choices = [];
                foreach ($this->relations = QubitRelation::getBySubjectOrObjectId($this->resource->id, ['typeId' => QubitTerm::TERM_RELATION_ASSOCIATIVE_ID]) as $item) {
                    $choices[$value[] = $this->context->routing->generate(null, [$item->object, 'module' => 'term'])] = $item->object;
                }

                $this->form->setDefault('relatedTerms', $value);
                $this->form->setValidator('relatedTerms', new sfValidatorPass());
                $this->form->setWidget('relatedTerms', new sfWidgetFormSelect(['choices' => $choices, 'multiple' => true]));

                break;

            case 'taxonomy':
                $this->form->setDefault('taxonomy', $this->context->routing->generate(null, [$this->resource->taxonomy, 'module' => 'taxonomy']));
                $this->form->setValidator('taxonomy', new sfValidatorString(['required' => true], ['required' => $this->context->i18n->__('This is a mandatory element.')]));

                $choices = [];
                if (isset($this->resource->taxonomy)) {
                    $choices[$this->context->routing->generate(null, [$this->resource->taxonomy, 'module' => 'taxonomy'])] = $this->resource->taxonomy;
                }

                if (isset($this->request->taxonomy)) {
                    $this->form->setDefault('taxonomy', $this->request->taxonomy);

                    $params = $this->context->routing->parse(Qubit::pathInfo($this->request->taxonomy));
                    $choices[$this->request->taxonomy] = $params['_sf_route']->resource;
                }

                $this->form->setWidget('taxonomy', new sfWidgetFormSelect(['choices' => $choices]));

                break;

            case 'useFor':
                $criteria = new Criteria();
                $criteria->add(QubitOtherName::OBJECT_ID, $this->resource->id);
                $criteria->add(QubitOtherName::TYPE_ID, QubitTerm::ALTERNATIVE_LABEL_ID);

                $value = $defaults = [];
                foreach ($this->useFor = QubitOtherName::get($criteria) as $item) {
                    $defaults[$value[] = $item->id] = $item;
                }

                $this->form->setDefault('useFor', $value);
                $this->form->setValidator('useFor', new sfValidatorPass());
                $this->form->setWidget('useFor', new QubitWidgetFormInputMany(['defaults' => $defaults]));

                break;

            case 'selfReciprocal':
                $this->form->setValidator('selfReciprocal', new sfValidatorBoolean());
                $this->form->setWidget('selfReciprocal', new sfWidgetFormInputCheckbox());

                if (isset($this->converseTerm) && $this->converseTerm->id == $this->resource->id) {
                    $this->form->setDefault('selfReciprocal', true);
                }

                break;

            default:
                return parent::addField($name);
        }
    }

    /**
     * Process form fields.
     *
     * @param $field mixed symfony form widget
     */
    protected function processField($field)
    {
        switch ($field->getName()) {
            case 'name':
                if (!QubitTerm::isProtected($this->resource->id)
                    && $this->resource->name != $this->form->getValue('name')) {
                    // Avoid duplicates (used in autocomplete.js)
                    if (filter_var($this->request->getPostParameter('linkExisting'), FILTER_VALIDATE_BOOLEAN)) {
                        $criteria = new Criteria();
                        $criteria->add(QubitTerm::TAXONOMY_ID, $this->resource->taxonomyId);
                        $criteria->addJoin(QubitTerm::ID, QubitTermI18n::ID);
                        $criteria->add(QubitTermI18n::CULTURE, $this->context->user->getCulture());
                        $criteria->add(QubitTermI18n::NAME, $this->form->getValue('name'));
                        if (null !== $term = QubitTerm::getOne($criteria)) {
                            $this->redirect([$term, 'module' => 'term']);

                            return;
                        }
                    }

                    $this->resource->name = $this->form->getValue('name');
                    $this->updatedLabel = true;
                }

                break;

            case 'narrowTerms':
                foreach ($this->form->getValue('narrowTerms') as $item) {
                    if (1 > strlen($item = trim($item))) {
                        continue;
                    }

                    // Test to make sure term doesn't already exist
                    $criteria = new Criteria();
                    $criteria->add(QubitTerm::TAXONOMY_ID, $this->resource->taxonomyId);
                    $criteria->addJoin(QubitTerm::ID, QubitTermI18n::ID);
                    $criteria->add(QubitTermI18n::CULTURE, $this->context->user->getCulture());
                    $criteria->add(QubitTermI18n::NAME, $item);
                    if (0 < count(QubitTermI18n::get($criteria))) {
                        continue;
                    }

                    // Add term as child
                    $term = new QubitTerm();
                    $term->name = $item;
                    $term->taxonomyId = $this->resource->taxonomyId;

                    $this->resource->termsRelatedByparentId[] = $term;
                }

                break;

            case 'parent':
                $this->resource->parentId = QubitTerm::ROOT_ID;

                $value = $this->form->getValue('parent');
                if (isset($value)) {
                    $params = $this->context->routing->parse(Qubit::pathInfo($value));
                    $this->resource->parent = $params['_sf_route']->resource;
                }

                break;

            case 'converseTerm':
                // Remove converse relations for this term
                foreach (QubitRelation::getBySubjectOrObjectId($this->resource->id, ['typeId' => QubitTerm::CONVERSE_TERM_ID]) as $converseRelation) {
                    $converseRelation->delete();
                }

                $value = $this->form->getValue('converseTerm');

                if (true === $this->form->getValue('selfReciprocal')) {
                    $this->resource->save();

                    // Set self-reciprocal relation
                    $relation = new QubitRelation();
                    $relation->typeId = QubitTerm::CONVERSE_TERM_ID;
                    $relation->object = $this->resource;

                    $this->resource->relationsRelatedBysubjectId[] = $relation;
                } elseif (isset($value) && '' != $value) {
                    // Create new converse relation
                    $relation = new QubitRelation();
                    $relation->typeId = QubitTerm::CONVERSE_TERM_ID;

                    // Get converse term, update parent and taxonomy (when it's created on the fly)
                    $params = $this->context->routing->parse(Qubit::pathInfo($value));
                    $converseTerm = $params['_sf_route']->resource;
                    $converseTerm->parentId = $this->resource->parentId;
                    $converseTerm->taxonomyId = $this->resource->taxonomyId;
                    $converseTerm->save();

                    // Remove converse relations for the converse term
                    foreach (QubitRelation::getBySubjectOrObjectId($converseTerm->id, ['typeId' => QubitTerm::CONVERSE_TERM_ID]) as $converseRelation) {
                        $converseRelation->delete();
                    }

                    $relation->object = $converseTerm;

                    $this->resource->relationsRelatedBysubjectId[] = $relation;
                }

                break;

            case 'taxonomy':
                unset($this->resource->taxonomy);

                $value = $this->form->getValue('taxonomy');
                if (isset($value)) {
                    $params = $this->context->routing->parse(Qubit::pathInfo($value));
                    $this->resource->taxonomy = $params['_sf_route']->resource;
                }

                break;

            case 'relatedTerms':
                $value = $filtered = [];
                foreach ($this->form->getValue('relatedTerms') as $item) {
                    $params = $this->context->routing->parse(Qubit::pathInfo($item));
                    $resource = $params['_sf_route']->resource;
                    $value[$resource->id] = $filtered[$resource->id] = $resource;
                }

                foreach ($this->relations as $item) {
                    if (isset($value[$item->objectId])) {
                        unset($filtered[$item->objectId]);
                    } else {
                        $item->delete();
                    }
                }

                foreach ($filtered as $item) {
                    $relation = new QubitRelation();
                    $relation->object = $item;
                    $relation->typeId = QubitTerm::TERM_RELATION_ASSOCIATIVE_ID;

                    $this->resource->relationsRelatedBysubjectId[] = $relation;
                }

                break;

            case 'useFor':
                $value = $filtered = $this->form->getValue('useFor');

                foreach ($this->useFor as $item) {
                    if (!empty($value[$item->id])) {
                        $item->name = $value[$item->id];
                        unset($filtered[$item->id]);
                    } else {
                        $item->delete();
                    }
                }

                foreach ($filtered as $item) {
                    if (!$item) {
                        continue;
                    }

                    $otherName = new QubitOtherName();
                    $otherName->name = $item;
                    $otherName->typeId = QubitTerm::ALTERNATIVE_LABEL_ID;

                    $this->resource->otherNames[] = $otherName;
                }

                break;

            default:
                return parent::processField($field);
        }
    }

    /**
     * Process form.
     */
    protected function processForm()
    {
        parent::processForm();

        // Check authorization
        if (!isset($this->getRoute()->resource) && !QubitAcl::check($this->resource->taxonomy, 'createTerm')) {
            QubitAcl::forwardUnauthorized();
        }

        $this->scopeNotesComponent->processForm();
        $this->sourceNotesComponent->processForm();
        $this->displayNotesComponent->processForm();

        // Update related info objects when term labels changes
        if ($this->updatedLabel) {
            $this->updateLinkedInfoObjects();
        }
    }

    protected function updateLinkedInfoObjects()
    {
        // Only update related IOs of terms that are fully added to the IOs in ES
        $allowedTaxonomyIds = [QubitTaxonomy::PLACE_ID, QubitTaxonomy::SUBJECT_ID, QubitTaxonomy::GENRE_ID];
        if (!isset($this->resource->taxonomyId) || !in_array($this->resource->taxonomyId, $allowedTaxonomyIds)) {
            return;
        }

        $ioIds = [];
        foreach ($this->resource->objectTermRelations as $item) {
            if ($item->object instanceof QubitInformationObject) {
                $ioIds[] = $item->objectId;
            }
        }

        if (0 == count($ioIds)) {
            return;
        }

        // Update asynchronously the linked IOs
        $jobOptions = [
            'ioIds' => $ioIds,
            'updateIos' => true,
            'updateDescendants' => false,
            'objectId' => $this->resource->id,
        ];
        QubitJob::runJob('arUpdateEsIoDocumentsJob', $jobOptions);

        // Let user know related descriptions update has started
        $jobsUrl = $this->context->routing->generate(null, ['module' => 'jobs', 'action' => 'browse']);
        $message = $this->context->i18n->__('Your term has been updated. Its related descriptions are being updated asynchronously – check the <a href="%1">job scheduler page</a> for status and details.', ['%1' => $jobsUrl]);
        $this->context->user->setFlash('notice', $message);
    }
}
