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

class arB5WidgetFormSchemaFormatter extends sfWidgetFormSchemaFormatter
{
    protected $errorListFormatInARow = '<div class="invalid-feedback"'
        ." id=\"%errors_id%\">\n  %errors%\n</div>\n";
    protected $errorRowFormatInARow = "<span>%error%</span>\n";
    protected $namedErrorRowFormatInARow = "<span>%name%: %error%</span>\n";
    protected $helpFormat = "<div class=\"form-text\" id=\"%help_id%\">\n"
        ."  %help%\n</div>\n";
    protected $name;

    public function generateLabelName($name)
    {
        $this->name = $name;
        $label = parent::generateLabelName($name);
        $validatorSchema = $this->form->getValidatorSchema();

        if (
            isset($validatorSchema[$name])
            && $validatorSchema[$name]->getOption('required')
        ) {
            $label .= '<span aria-hidden="true" class="text-primary ms-1">'
                .'<strong>*</strong></span>'
                .'<span class="visually-hidden">'
                .__('This field is required.')
                .'</span>';
        }

        return $label;
    }

    public function getErrorListFormatInARow()
    {
        return strtr(
            $this->errorListFormatInARow,
            ['%errors_id%' => $this->name.'-errors']
        );
    }

    public function getHelpFormat()
    {
        return strtr(
            $this->helpFormat,
            ['%help_id%' => $this->name.'-help']
        );
    }

    public function getRowFormat()
    {
        // HACK ->formatRow() lacks access to lots of information about the field,
        // including the name.  So to add the name to the row markup, we must
        // either,
        //  * Add it outside ->formatRow(), perhaps using FluentDOM
        //  * Extract the name from the field markup with regex
        //  * Take advantage that ->renderRow() always calls ->renderLabel(), which
        //    calls ->generateLabel(), before ->renderRow()
        return <<<return
<div class="mb-3 form-row-{$this->name}">
  %label%
  %error%%field%
  %help%
  %hidden_fields%
</div>

return;
    }
}