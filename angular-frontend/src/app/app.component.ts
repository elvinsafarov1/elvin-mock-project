import { Component, OnInit } from '@angular/core';
import { UserService } from './user.service';

interface User {
  id?: number;
  name: string;
  email: string;
  external_data?: any;
}

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.css']
})
export class AppComponent implements OnInit {
  title = 'Elvin Mock Project';
  users: User[] = [];
  selectedUser: User | null = null;
  newUser: User = { name: '', email: '' };
  loading = false;

  constructor(private userService: UserService) {}

  ngOnInit(): void {
    this.loadUsers();
  }

  loadUsers(): void {
    this.loading = true;
    this.userService.getUsers().subscribe({
      next: (users) => {
        this.users = users;
        this.loading = false;
      },
      error: (error) => {
        console.error('Error loading users:', error);
        this.loading = false;
      }
    });
  }

  selectUser(user: User): void {
    if (user.id) {
      this.loading = true;
      this.userService.getUser(user.id).subscribe({
        next: (userData) => {
          this.selectedUser = userData;
          this.loading = false;
        },
        error: (error) => {
          console.error('Error loading user:', error);
          this.loading = false;
        }
      });
    }
  }

  createUser(): void {
    if (this.newUser.name && this.newUser.email) {
      this.loading = true;
      this.userService.createUser(this.newUser).subscribe({
        next: (user) => {
          this.users.push(user);
          this.newUser = { name: '', email: '' };
          this.loading = false;
        },
        error: (error) => {
          console.error('Error creating user:', error);
          this.loading = false;
        }
      });
    }
  }
}

