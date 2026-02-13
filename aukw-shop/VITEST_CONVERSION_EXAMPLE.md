# Vitest Test Conversion Example

## ✅ What Was Converted

### File: `payroll-calculation.service.spec.ts`

**Changes Made:**
1. ✅ Removed TestBed (not needed for pure services)
2. ✅ Added explicit Vitest imports
3. ✅ Simplified service instantiation

### Before (Karma/Jasmine):
```typescript
import { TestBed } from '@angular/core/testing';
import {
  PayrollCalculationService,
  LineItemDetail,
} from './payroll-calculation.service';

describe('PayrollCalculationService', () => {
  let service: PayrollCalculationService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(PayrollCalculationService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
  // ... more tests
});
```

### After (Vitest):
```typescript
import { describe, it, expect, beforeEach } from 'vitest';
import {
  PayrollCalculationService,
  LineItemDetail,
} from './payroll-calculation.service';

describe('PayrollCalculationService', () => {
  let service: PayrollCalculationService;

  beforeEach(() => {
    // No TestBed needed - pure service with no dependencies
    service = new PayrollCalculationService();
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
  // ... more tests (no changes needed - syntax compatible!)
});
```

## ⚠️ Current Blocker: Angular 21 + Analog Plugin

**Error:**
```
JIT compilation failed for injectable 'PlatformLocation'
The injectable needs to be compiled using the JIT compiler,
but '@angular/compiler' is not available.
```

**Root Cause:** The `@analogjs/vite-plugin-angular` v2.2.3 may not fully support Angular 21.1.2 yet, causing JIT compilation issues.

## 🔧 Solutions to Try

### Solution 1: Wait for Analog Plugin Update (Recommended if not urgent)
Angular 21 is very recent (January 2025). The Analog team may need to release an update for full compatibility.

**Check for updates:**
```bash
npm outdated @analogjs/vite-plugin-angular
npm update @analogjs/vite-plugin-angular --legacy-peer-deps
```

### Solution 2: Use Jest Instead of Vitest (Proven Solution)
Jest has mature Angular support and works well with Angular 21:

```bash
npm install -D jest @types/jest jest-preset-angular
```

Jest configuration is simpler for Angular and has extensive documentation.

### Solution 3: Downgrade to Angular 19/20 (Not Recommended)
Only if tests are critical and you can't wait for Analog updates.

### Solution 4: Mock Angular Dependencies
For services without Angular dependencies, create lightweight mocks:

```typescript
// Mock IrisPayslip as plain object instead of importing
interface MockPayslip {
  payrollNumber: number;
  totalPay: number;
  // ... other properties
}

function createPayslip(payrollNumber: number, totalPay: number): MockPayslip {
  return { payrollNumber, totalPay /* ... */ };
}
```

This avoids importing Angular-dependent models.

### Solution 5: Run Tests in Node Environment
Update vitest.config.ts:

```typescript
export default defineConfig({
  test: {
    environment: 'node', // Instead of 'jsdom'
    // ...
  },
});
```

This may work for pure logic tests but won't work for component tests.

## 📊 Test Conversion Patterns

### Pattern 1: Pure Services (No Dependencies)
```typescript
// Before
beforeEach(() => {
  TestBed.configureTestingModule({});
  service = TestBed.inject(MyService);
});

// After
beforeEach(() => {
  service = new MyService();
});
```

### Pattern 2: Services with Dependencies
```typescript
// Before
beforeEach(() => {
  const httpSpy = jasmine.createSpyObj('HttpClient', ['get', 'post']);
  TestBed.configureTestingModule({
    providers: [
      MyService,
      { provide: HttpClient, useValue: httpSpy }
    ]
  });
  service = TestBed.inject(MyService);
});

// After
beforeEach(() => {
  const httpMock = {
    get: vi.fn(),
    post: vi.fn(),
  };
  service = new MyService(httpMock as any);
});
```

### Pattern 3: Jasmine Spies → Vitest Mocks
```typescript
// Before (Jasmine)
const spy = jasmine.createSpyObj('ServiceName', ['method1', 'method2']);
spy.method1.and.returnValue(of({ data: 'test' }));
spy.method2.and.callFake((param) => param * 2);

// After (Vitest)
const spy = {
  method1: vi.fn().mockReturnValue(of({ data: 'test' })),
  method2: vi.fn().mockImplementation((param) => param * 2),
};
```

### Pattern 4: Async Tests (Same Syntax!)
```typescript
// Both Jasmine and Vitest support this syntax ✅
it('should handle async operations', async () => {
  const result = await service.fetchData();
  expect(result).toBeDefined();
});

// Or with done callback
it('should work with callback', (done) => {
  service.getData().subscribe(result => {
    expect(result).toBeTruthy();
    done();
  });
});
```

## 📝 Conversion Checklist

When converting a test file:

- [ ] Replace `jasmine.createSpyObj` with plain objects + `vi.fn()`
- [ ] Replace `spy.and.returnValue()` with `spy.mockReturnValue()`
- [ ] Replace `spy.and.callFake()` with `spy.mockImplementation()`
- [ ] Add `import { describe, it, expect, beforeEach, vi } from 'vitest'`
- [ ] Remove TestBed for pure services (or simplify TestBed usage)
- [ ] Keep `expect()` assertions (compatible with both!)
- [ ] Keep `describe()` and `it()` structure (compatible!)
- [ ] Keep async/await patterns (compatible!)

## 🎯 What's Working

Even though tests aren't running yet, we've successfully:

1. ✅ Installed and configured Vitest
2. ✅ Fixed circular dependencies
3. ✅ Configured TypeScript path mappings
4. ✅ Created proper test setup files
5. ✅ Converted one test file to Vitest syntax
6. ✅ Identified the blocking issue (Analog + Angular 21)

## 🚦 Recommendation

**For immediate testing needs:** Consider using **Jest** instead, which has proven Angular 21 support.

**For long-term:** Monitor the `@analogjs/vite-plugin-angular` repository for Angular 21 compatibility updates, or contribute a fix if urgent.

**For this project:** The test conversion pattern shown above is valid. Once the Analog plugin issue is resolved, all tests should work with minimal changes.

---

**Created:** February 12, 2026
**Status:** Vitest setup complete, waiting for Angular 21 compatibility
**Conversion:** 1 file converted successfully (syntax-wise)
