import {actionClient, showError, showNotice} from '@craftcms/cp';

export interface ActionRequestOptions {
  data?: unknown;
  params?: Record<string, unknown>;
  responseType?: 'json' | 'blob' | 'text';
}

export function sendActionRequest<T = unknown>(
  method: string,
  action: string,
  options: ActionRequestOptions = {}
) {
  return actionClient.request<T>({method, url: action, ...options});
}

export function displayNotice(message: string): void {
  showNotice(message);
}

export function displayError(message?: string | null): void {
  showError(message);
}
