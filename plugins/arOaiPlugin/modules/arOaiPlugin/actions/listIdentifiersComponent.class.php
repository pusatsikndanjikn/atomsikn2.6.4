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

/**
 * Generate  listIdentifiers response of the OAI-PMH protocol for the Access to Memory (AtoM).
 *
 * @author     Mathieu Fortin Library and Archives Canada <mathieu.fortin@lac-bac.gc.ca>
 */
class arOaiPluginListIdentifiersComponent extends arOaiPluginComponent
{
    public function execute($request)
    {
        $options = [];

        $this->setUpdateParametersFromRequest($request);

        // Restrict to top-level descriptions for EAD given children get nested
        if ('oai_ead' == $request->metadataPrefix) {
            $options['topLevel'] = true;
        }

        $this->getUpdates($options);

        $this->setRequestAttributes($request);
    }
}
