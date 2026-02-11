/**
 * API message types
 */
export type ApiMessageType = 'info' | 'warning' | 'success' | 'error';

/**
 * Individual message from the API
 */
export interface ApiMessage {
  /** Message type/severity */
  type: ApiMessageType;
  /** Human-readable message */
  message: string;
  /** Additional context data */
  context?: any;
  /** When the message was created */
  timestamp?: string;
  /** Object ID */
  id?: number;
}

/**
 * Standardized API response structure
 *
 * Allows the backend to return data along with informational messages
 * without stopping processing
 */
export interface ApiResponse<T = any> {
  /** Whether the operation succeeded */
  success: boolean;
  /** The response data */
  data: T;
  /** Array of messages (info, warnings, errors) */
  messages: ApiMessage[];
}

/**
 * Response from the API when uploading a file
 */
export class UploadResponse {
  isEncrypted: boolean;
  message: string;
  filename: string;

  constructor(obj?: any) {
    this.isEncrypted = (obj && obj.isEncrypted) || false;
    this.message = (obj && obj.message) || null;
    this.filename = (obj && obj.filename) || null;
  }
}
