/**
 * Unit tests for ProjectAllocationsValidater
 *
 * This is a form-level validator that:
 * - Ensures allocation percentages in a FormArray sum to exactly 100
 * - Sets errors on individual FormControls (not as a return value)
 * - Detects duplicate project names
 * - Detects the case where all project names are empty
 *
 * The validator always returns null — errors are communicated by
 * calling setErrors() directly on child controls.
 */

import { FormArray, FormControl, FormGroup } from '@angular/forms';
import { ProjectAllocationsValidater } from './allocations.validator';

describe('ProjectAllocationsValidater', () => {
  // ==========================================
  // PERCENTAGE SUM VALIDATION
  // ==========================================

  describe('percentage sum', () => {
    it('should set no error when a single allocation is 100%', () => {
      const form = buildForm([{ project: 'Admin', percentage: 100 }]);

      const lastPercentageControl = lastPercentage(form);

      expect(lastPercentageControl.errors).toBeNull();
    });

    it('should set no error when two allocations sum to 100%', () => {
      const form = buildForm([
        { project: 'Admin', percentage: 60 },
        { project: 'Operations', percentage: 40 },
      ]);

      expect(lastPercentage(form).errors).toBeNull();
    });

    it('should set percentagesMustSumToPar error on the last control when total is less than 100%', () => {
      const form = buildForm([
        { project: 'Admin', percentage: 60 },
        { project: 'Operations', percentage: 30 },
      ]);

      expect(lastPercentage(form).errors).toEqual({
        percentagesMustSumToPar: true,
      });
    });

    it('should set error when total exceeds 100%', () => {
      const form = buildForm([
        { project: 'Admin', percentage: 60 },
        { project: 'Operations', percentage: 60 },
      ]);

      expect(lastPercentage(form).errors).toEqual({
        percentagesMustSumToPar: true,
      });
    });

    it('should clear the error once percentages are corrected to 100%', () => {
      const form = buildForm([
        { project: 'Admin', percentage: 60 },
        { project: 'Operations', percentage: 30 },
      ]);
      expect(lastPercentage(form).errors).not.toBeNull();

      // Fix the percentage
      const allocations = form.controls['allocations'] as FormArray;
      (allocations.at(1).get('percentage') as FormControl).setValue(40);
      form.updateValueAndValidity();

      expect(lastPercentage(form).errors).toBeNull();
    });
  });

  // ==========================================
  // DUPLICATE PROJECT VALIDATION
  // ==========================================

  describe('duplicate project names', () => {
    it('should set duplicateProject error when the same project appears twice', () => {
      const form = buildForm([
        { project: 'Admin', percentage: 50 },
        { project: 'Admin', percentage: 50 }, // duplicate
      ]);

      const allocations = form.controls['allocations'] as FormArray;
      const duplicateProjectControl = allocations.at(1).get('project');

      expect(duplicateProjectControl?.errors).toEqual({
        duplicateProject: true,
      });
    });

    it('should not set duplicateProject error when all project names are unique', () => {
      const form = buildForm([
        { project: 'Admin', percentage: 50 },
        { project: 'Operations', percentage: 50 },
      ]);

      const allocations = form.controls['allocations'] as FormArray;
      const projectControl = allocations.at(1).get('project');

      expect(projectControl?.errors).toBeNull();
    });
  });

  // ==========================================
  // EMPTY PROJECT NAME VALIDATION
  // ==========================================

  describe('empty project names', () => {
    it('should set required error on the last project control when all project names are empty', () => {
      const form = buildForm([
        { project: '', percentage: 50 },
        { project: '', percentage: 50 },
      ]);

      const allocations = form.controls['allocations'] as FormArray;
      const lastProjectControl = allocations.at(1).get('project');

      expect(lastProjectControl?.errors).toEqual({ required: true });
    });

    it('should not set required error when at least one project name is provided', () => {
      const form = buildForm([
        { project: 'Admin', percentage: 50 },
        { project: '', percentage: 50 },
      ]);

      const allocations = form.controls['allocations'] as FormArray;
      const lastProjectControl = allocations.at(1).get('project');

      expect(lastProjectControl?.errors).toBeNull();
    });
  });

  // ==========================================
  // RETURN VALUE
  // ==========================================

  it('should always return null (errors are set on child controls, not returned)', () => {
    const form = buildForm([{ project: 'Admin', percentage: 50 }]);
    const validator = ProjectAllocationsValidater('allocations');

    const result = validator(form);

    expect(result).toBeNull();
  });

  // ==========================================
  // HELPERS
  // ==========================================

  function buildForm(
    items: { project: string; percentage: number }[],
  ): FormGroup {
    const allocationsArray = new FormArray(
      items.map(
        (item) =>
          new FormGroup({
            project: new FormControl(item.project),
            percentage: new FormControl(item.percentage),
          }),
      ),
    );
    return new FormGroup(
      { allocations: allocationsArray },
      { validators: ProjectAllocationsValidater('allocations') },
    );
  }

  function lastPercentage(form: FormGroup): FormControl {
    const allocations = form.controls['allocations'] as FormArray;
    return allocations.at(allocations.length - 1).get(
      'percentage',
    ) as FormControl;
  }
});
