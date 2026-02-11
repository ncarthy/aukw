import { Injectable, inject } from '@angular/core';
import { AlertService } from './alert.service';
import { ApiResponse, ApiMessage, ApiMessageType } from '@app/_models/api-response';

/**
 * Service for handling API responses with messages
 *
 * Automatically displays info, warning, success, and error messages
 * from API responses to the user via the AlertService
 */
@Injectable({ providedIn: 'root' })
export class ApiResponseHandler {
  private alertService = inject(AlertService);

  /**
   * Handle API response and display any messages to the user
   *
   * @param response The API response object
   * @returns The data from the response
   *
   * @example
   * ```typescript
   * this.http.get<ApiResponse<PayslipData>>('/api/payroll')
   *   .pipe(map(response => this.responseHandler.handleResponse(response)))
   *   .subscribe(data => {
   *     // User has already seen any info/warning messages
   *     // Process data normally
   *   });
   * ```
   */
  handleResponse<T>(response: ApiResponse<T>): T {
    // Display all messages to the user
    if (response.messages && response.messages.length > 0) {
      response.messages.forEach(msg => this.displayMessage(msg));
    }

    return response.data;
  }

  /**
   * Display a single message using the alert service
   *
   * @param message The message to display
   */
  private displayMessage(message: ApiMessage): void {
    const options = {
      autoClose: message.type === 'success' || message.type === 'info',
      keepAfterRouteChange: false
    };

    switch (message.type) {
      case 'info':
        this.alertService.info(message.message, options);
        break;
      case 'warning':
        this.alertService.warn(message.message, options);
        break;
      case 'success':
        this.alertService.success(message.message, options);
        break;
      case 'error':
        this.alertService.error(message.message, options);
        break;
    }
  }

  /**
   * Get messages of a specific type from a response
   *
   * @param response The API response
   * @param type The message type to filter by
   * @returns Array of messages matching the type
   */
  getMessagesByType(response: ApiResponse, type: ApiMessageType): ApiMessage[] {
    return response.messages.filter(msg => msg.type === type);
  }

  /**
   * Check if response has any warnings or errors
   *
   * @param response The API response
   * @returns True if there are warnings or errors
   */
  hasIssues(response: ApiResponse): boolean {
    return response.messages.some(
      msg => msg.type === 'warning' || msg.type === 'error'
    );
  }

  /**
   * Check if response has info messages
   *
   * @param response The API response
   * @returns True if there are info messages
   */
  hasInfoMessages(response: ApiResponse): boolean {
    return response.messages.some(msg => msg.type === 'info');
  }

  /**
   * Get a summary of all messages
   *
   * @param response The API response
   * @returns Object with counts by type
   */
  getMessageSummary(response: ApiResponse): {
    info: number;
    warning: number;
    success: number;
    error: number;
  } {
    return {
      info: this.getMessagesByType(response, 'info').length,
      warning: this.getMessagesByType(response, 'warning').length,
      success: this.getMessagesByType(response, 'success').length,
      error: this.getMessagesByType(response, 'error').length
    };
  }
}
