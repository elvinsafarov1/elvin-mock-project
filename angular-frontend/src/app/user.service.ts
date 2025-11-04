import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

interface User {
  id?: number;
  name: string;
  email: string;
  external_data?: any;
}

@Injectable({
  providedIn: 'root'
})
export class UserService {
  private apiUrl = (window as any).__API_URL__ || 'http://localhost:8000';

  constructor(private http: HttpClient) {}

  getUsers(): Observable<User[]> {
    return this.http.get<User[]>(`${this.apiUrl}/api/users`);
  }

  getUser(id: number): Observable<User> {
    return this.http.get<User>(`${this.apiUrl}/api/users/${id}`);
  }

  createUser(user: User): Observable<User> {
    return this.http.post<User>(`${this.apiUrl}/api/users`, user);
  }
}

