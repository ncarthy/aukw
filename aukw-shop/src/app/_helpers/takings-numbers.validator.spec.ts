/**
 * Unit tests for MustProvideNumberOfItems validator
 *
 * Validates that if a department sales value is provided,
 * the corresponding item count must also be provided.
 * Controls are named '<controlName>' and '<controlName>_num'.
 */

import { FormControl, FormGroup } from '@angular/forms';
import { MustProvideNumberOfItems } from './takings-numbers.validator';

describe('MustProvideNumberOfItems', () => {
  function buildForm(salesValue: any, numItems: any): FormGroup {
    return new FormGroup(
      { clothing: new FormControl(salesValue), clothing_num: new FormControl(numItems) },
      { validators: MustProvideNumberOfItems('clothing') },
    );
  }

  it('should set no error when both sales value and item count are provided', () => {
    const form = buildForm(150, 10);

    expect(form.controls['clothing_num'].errors).toBeNull();
  });

  it('should set an error on the _num control when sales value is provided but item count is missing', () => {
    const form = buildForm(150, null);

    expect(form.controls['clothing_num'].errors).toEqual({
      mustProvideNumberOfItems: true,
    });
  });

  it('should clear any existing error when both values are provided', () => {
    const form = buildForm(150, null);
    expect(form.controls['clothing_num'].errors).not.toBeNull();

    form.controls['clothing_num'].setValue(5);
    form.updateValueAndValidity();

    expect(form.controls['clothing_num'].errors).toBeNull();
  });

  it('should set no error when both sales value and item count are absent', () => {
    const form = buildForm(null, null);

    expect(form.controls['clothing_num'].errors).toBeNull();
  });

  it('should set no error when sales value is zero (treated as absent)', () => {
    // 0 is falsy, so no item count is required
    const form = buildForm(0, null);

    expect(form.controls['clothing_num'].errors).toBeNull();
  });

  it('should set no error when sales value is absent but item count is provided', () => {
    const form = buildForm(null, 5);

    expect(form.controls['clothing_num'].errors).toBeNull();
  });

  it('should always return null from the validator itself (errors set on controls directly)', () => {
    const form = buildForm(150, null);
    const validator = MustProvideNumberOfItems('clothing');

    const result = validator(form);

    expect(result).toBeNull();
  });
});
