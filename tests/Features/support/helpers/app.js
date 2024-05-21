let faker = require('faker');
let _ = require('lodash');

// Create a user
Cypress.Commands.add('login', (callback) => {
  cy.exec('php artisan setup:frontendtestuser').then((result) => {
    cy.loginin(result.stdout);
    cy.visit('/home');
    if (callback !== undefined) {
      callback(result.stdout);
    }
  });
});

Cypress.Commands.add('loginin', (id) => {
  cy.visit('/_dusk/login/'+id+'/');
});

Cypress.Commands.add('loginLegacy', (role) => {
  cy.exec('php artisan setup:frontendtesting -vvv');

  cy.visit('/login');

  cy.get('input[name=email]').type('admin@admin.com');
  cy.get('input[name=password]').type('admin');

  cy.get('button[type=submit]').click();

  cy.wait(1000);

  cy.url().should('include', '/home');
});

Cypress.Commands.add('logout', () => {
  cy.get('[data-cy=header-menu]').click();
  cy.get('[data-cy=logout-button]').click();
  cy.wait(500);
});

// Create a company called "Dunder Mifflin"
Cypress.Commands.add('createCompany', (companyName, callback) => {
  cy.get('[data-cy=create-company-blank-state]').click();

  cy.url().should('include', '/company/create');

  let _companyName = (typeof companyName === 'string') ? companyName : faker.company.companyName();
  let _callback = (typeof companyName === 'function') ? companyName : ((typeof callback === 'function') ? callback : undefined);

  cy.log('callback: '+ (typeof _callback));

  cy.get('input[name=name]').type(_companyName);
  cy.get('[data-cy=create-company-submit]').click();
  cy.wait(500);

  cy.get('[data-cy=company-welcome]', { timeout: 30000 }).should('be.visible')
    .invoke('attr', 'data-cy-item').then(function (companyId) {
      if (_callback !== undefined) {
        _callback(companyId);
      }
    });
});

// Create a team
Cypress.Commands.add('createTeam', (productName) => {
  cy.get('[data-cy=header-adminland-link]').click();

  cy.get('[data-cy=team-admin-link]').click();
  cy.get('[data-cy=add-team-button]').click();

  cy.get('input[name=name]').type(productName);

  cy.get('[data-cy=submit-add-team-button]').click();
});

// Create an employee
Cypress.Commands.add('createEmployee', (firstname, lastname, email, permission, sendEmail, callback) => {
  cy.get('[data-cy=header-adminland-link]', {timeout: 500}).click();

  cy.get('[data-cy=employee-admin-link]', {timeout: 500}).click();
  cy.url().should('include', '/account/employees');

  cy.get('[data-cy=add-employee-button]', {timeout: 500}).click();

  cy.get('input[name=first_name]').type(firstname);
  cy.get('input[name=last_name]').type(lastname);
  cy.get('input[name=email]').type(email);

  switch (permission) {
  case 'admin':
    cy.get('[type="radio"]').first().check();
    break;
  case 'hr':
    cy.get('[type="radio"]').check(['200']);
    break;
  case 'user':
    cy.get('[type="radio"]').check(['300']);
    break;
  }

  if (sendEmail === true) {
    cy.get('[data-cy=send-email]').check();
  }

  cy.get('[data-cy=submit-add-employee-button]').click();

  cy.get('[data-cy=all-employee-link]', {timeout: 800}).should('be.visible').as('all-employee-link');

  if (callback !== undefined) {
    cy.get('@all-employee-link').click();
    cy.get('[data-cy=employee-list]').should('be.visible')
      .invoke('attr', 'data-cy-items').then(function (items) {
        let employeeId = _.last(items.split(','));
        callback(employeeId);
      });
  }
});

// Finalize account creation and go to the dashboard
Cypress.Commands.add('acceptInvitationLinkAndGoToDashboard', (password, link) => {
  cy.logout();
  cy.visit('/invite/employee/' + link);
  cy.get('[data-cy=accept-create-account]').click();

  cy.get('input[name=password]').type(password);
  cy.get('[data-cy=create-cta]').click();
  cy.exec('php artisan setup:verify-email').then((result) => {
    cy.visit('/home');
    cy.get('[data-cy=company-1]').click();
  });
});

// Assert that the page can be visited by a user with the right permission level
Cypress.Commands.add('canAccess', (url, permission, textToSee, userId = 1) => {
  cy.changePermission(userId, permission);
  cy.visit(url);
  cy.contains(textToSee);
});

// Assert that a page can not be visited
Cypress.Commands.add('canNotAccess', (url, permission, userId = 1) => {
  cy.changePermission(userId, permission);
  cy.request({
    url: url,
    failOnStatusCode: false
  })
    .then((resp) => {
      expect(resp.status).to.eq(401);
    });
});

// Assert that an audit log has been created with the following content
// and redirect the page to the given url
Cypress.Commands.add('hasAuditLog', (content, redirectUrl, companyId = 1) => {
  cy.visit('/'+companyId+'/account/audit');
  cy.contains(content);
  if (redirectUrl) {
    cy.visit(redirectUrl);
  }
});

// Assert that an employee log has been created with the following content
// and redirect the page to the given url
Cypress.Commands.add('hasEmployeeLog', (content, redirectUrl, visitUrl = '', companyId = 1, userId = 1) => {
  if (visitUrl != '') {
    cy.visit(visitUrl);
  } else {
    cy.visit('/'+companyId+'/employees/'+userId+'/logs');
  }

  cy.contains(content);

  cy.visit(redirectUrl);
});

// Assert that a team log has been created with the following content
// and redirect the page to the given url
Cypress.Commands.add('hasTeamLog', (content, redirectUrl, companyId = 1, userId = 1) => {
  cy.visit('/'+companyId+'/account/teams/'+userId+'/logs');

  cy.contains(content);

  cy.visit(redirectUrl);
});

// Assert that the employee has a notification
Cypress.Commands.add('hasNotification', (content, companyId = 1) => {
  cy.visit('/'+companyId+'/notifications');
  cy.contains(content);
});

// Assign the employee as the manager
Cypress.Commands.add('assignManager', (name) => {
  cy.get('[data-cy=add-hierarchy-button]').click();
  cy.get('[data-cy=add-manager-button]').click();
  cy.get('[data-cy=search-manager]').type(name);
  cy.get('[data-cy=potential-manager-button').click();
});

// Give the accountant right to the employee
Cypress.Commands.add('grantAccountantRight', (name, employeeNumber) => {
  cy.get('[data-cy=header-adminland-link]').click();
  cy.get('[data-cy=expenses-admin-link]').click();
  cy.get('[data-cy=show-edit-mode]').click();
  cy.get('[data-cy=hide-edit-mode]').click();
  cy.get('[data-cy=show-edit-mode]').click();
  cy.get('[data-cy=potential-employees]').type(name);
  cy.get('[data-cy=employee-id-' + employeeNumber + '-add]').click();
  cy.get('[data-cy=hide-edit-mode]').click();
});

// Change persmission of the user
Cypress.Commands.add('changePermission', (userId, permission) => {
  cy.exec('php artisan test:changepermission --user=' + userId + ' --permission=' + permission);
});

// Assign an employee to a team
Cypress.Commands.add('assignEmployeeToTeam', (employeeId, teamId, companyId = 1) => {
  cy.visit('/'+companyId+'/employees/'+employeeId);

  // Open the modal to assign a team and select the first line
  cy.get('[data-cy=open-team-modal-blank]').click();
  cy.get('[data-cy=list-team-' + teamId + ']').click();
  cy.get('.existing-teams').contains('product');
});

// Create a recent ship entry
Cypress.Commands.add('createRecentShip', (featureName, description = '', name = '', employeePosition = 0) => {
  // create an entry with title+description+employees
  cy.get('[data-cy=add-recent-ship-entry]').click();
  cy.get('[data-cy=recent-ship-title-input').type(featureName);

  if (description != '') {
    cy.get('[data-cy=ship-add-description]').click();
    cy.get('[data-cy=ship-description]').type(description);
  }

  if (name != '') {
    cy.get('[data-cy=ship-add-employees]').click();
    cy.get('[data-cy=ship-employees]').type(name);
    cy.get('[data-cy=employee-id-' + employeePosition + ']', { timeout: 600 }).should('be.visible').click();
  }

  cy.get('[data-cy=submit-add-ship-button]').click();
});

// Check that the recent ship entry exists
Cypress.Commands.add('readRecentShipEntry', (title, description, employeeName, employeeId) => {
  cy.get('[data-cy=recent-ship-title]').contains(title);
  cy.get('[data-cy=recent-ship-description]').contains(description);
  cy.get('[data-cy=ship-list-employee-' + employeeId + ']').contains(employeeName);
});

// Create an expense
Cypress.Commands.add('createExpense', (title, amount, companyId = 1) => {
  cy.visit('/'+companyId+'/dashboard');
  cy.get('[data-cy=create-expense-cta]').click();
  cy.get('[data-cy=expense-create-cancel]').click();
  cy.get('[data-cy=create-expense-cta]').click();
  cy.get('[data-cy=expense-amount]').type(amount);
  cy.get('[data-cy=expense-currency]').click();
  cy.get('ul.vs__dropdown-menu>li').eq(4).click();
  cy.get('[data-cy=expense-currency]').click();
  cy.get('[data-cy=expense-title]').type(title);
  cy.get('[data-cy=submit-expense]').click();
});

// Create a project
Cypress.Commands.add('createProject', (companyId = 1, name, code = '', summary = '', projectLeadId = '') => {
  cy.visit('/' + companyId +'/company/projects/create');
  cy.get('[data-cy=project-name-input]').type(name);

  if (code != '') {
    cy.get('[data-cy=add-code]').click();
    cy.get('[data-cy=project-code-input]').type(code);
  }

  if (summary != '') {
    cy.get('[data-cy=add-summary]').click();
    cy.get('[data-cy=project-summary-input]').type(summary);
  }

  if (projectLeadId != '') {
    cy.get('[data-cy=project-assign-project-lead]').click();
    cy.get('[data-cy=project-lead-search]').type('admin');
    cy.wait(600);
    cy.get('[data-cy=employee-id-1]').click();
  }

  cy.get('[data-cy=submit-create-project-button]').click();
  cy.wait(1000);
});

// Create project task list
Cypress.Commands.add('createProjectTaskList', (companyId = 1, projectId = 1, listTitle = 'title', listContent = 'content') => {
  let _listTitle = (typeof listTitle === 'string') ? listTitle : faker.company.catchPhrase();
  let _listContent = (typeof listContent === 'string') ? listContent : faker.realText(50);

  cy.visit('/' + companyId + '/company/projects/' + projectId + '/tasks');
  cy.get('[data-cy=new-task-list-cta]').click();
  cy.get('[data-cy=task-list-title-input]').type(_listTitle);
  cy.get('[data-cy=task-list-description]').type(_listContent);
  cy.get('[data-cy=store-task-list-cta]').click();

  // make sure the list exists
  cy.get('[data-cy=task-list-1]').contains(_listTitle);
});

// Create project task
Cypress.Commands.add('createProjectTask', (companyId = 1, projectId = 1, projectTaskListId = 1, title = 'title') => {
  let _title = (typeof title === 'string') ? title : faker.company.catchPhrase();

  cy.visit('/' + companyId + '/company/projects/' + projectId + '/tasks');
  cy.get('[data-cy=task-list-' + projectTaskListId + '-add-new-task]').click();
  cy.get('[data-cy=task-list-' + projectTaskListId + '-task-title-textarea]').type(_title);
  cy.get('[data-cy=task-list-' + projectTaskListId + '-add-task-cta]').click();

  // make sure the task exists
  cy.get('[data-cy=task-1]').contains(_title);
});

// Create an employee status
Cypress.Commands.add('createEmployeeStatus', (companyId = 1, name, external = true) => {
  cy.visit('/' + companyId + '/account/employeestatuses');

  cy.get('[data-cy=add-status-button]').click();
  cy.get('[data-cy=add-title-input]').type(name);
  if (external == true) {
    cy.get('[data-cy=external-employee-checkbox]').check();
  }
  cy.get('[data-cy=modal-add-cta]').click();

  // to refresh the page
  cy.visit('/' + companyId + '/account/employeestatuses');
});

// Set the contract renewal date to X days
Cypress.Commands.add('setContractRenewalDate', (companyId = 1, employeeId = 1, numberOfDaysMore = 3) => {
  cy.visit('/' + companyId + '/employees/' + employeeId + '/contract/edit');
  cy.get('input[name=year]').clear();
  cy.get('input[name=year]').type(Cypress.moment().add(numberOfDaysMore, 'days').year());
  cy.get('input[name=month]').clear();
  cy.get('input[name=month]').type(Cypress.moment().add(numberOfDaysMore, 'days').month() + 1);
  cy.get('input[name=day]').clear();
  cy.get('input[name=day]').type(Cypress.moment().add(numberOfDaysMore, 'days').date());
  cy.get('[data-cy=submit-edit-contract-employee-button]').click();
});

// Set the contract rate
Cypress.Commands.add('setContractRate', (companyId = 1, employeeId = 1, rate = 10) => {
  cy.visit('/' + companyId + '/employees/' + employeeId + '/contract/edit');

  cy.get('[data-cy=add-rate-button]').click();
  cy.get('[data-cy=add-rate-input]').clear();
  cy.get('[data-cy=add-rate-input]').type(rate);
  cy.get('[data-cy=modal-add-rate-cta]').click();
});

// fill and submit timesheet for time tracking
Cypress.Commands.add('fillAndSubmitTimesheet', (companyId = 1) => {
  cy.visit('/' + companyId + '/dashboard/timesheet');

  // add a new row
  cy.get('[data-cy=timesheet-add-new-row]').click();
  cy.get('[data-cy=project-selector]').click();
  cy.get('ul.vs__dropdown-menu>li').eq(0).click();

  cy.get('[data-cy=task-selector]').click();
  cy.get('ul.vs__dropdown-menu>li').eq(0).click();
  cy.get('[data-cy=submit-timesheet-new-row] > span').click();

  // fill the newly created row
  cy.get('[data-cy=timesheet-1-day-0-hours]').type('1');
  cy.get('[data-cy=timesheet-1-day-0-minutes]').type('30');
  cy.get('[data-cy=timesheet-1-day-1-hours]').type('1');
  cy.get('[data-cy=timesheet-1-day-1-minutes]').type('30');
  cy.get('[data-cy=timesheet-1-day-4-hours]').type('1');
  cy.get('[data-cy=timesheet-1-day-4-minutes]').type('30');

  // submit timesheets
  cy.get('[data-cy=timesheet-submit-timesheet]').click();
  cy.get('[data-cy=timesheet-status-awaiting]').should('exist');
});

// Set the birthdate
Cypress.Commands.add('setBirthdate', (companyId = 1, employeeId = 1, firstName, lastName, email, year, month, day) => {
  cy.visit('/' + companyId + '/employees/' + employeeId);
  cy.get('[data-cy=edit-important-date-link]').click();
  cy.get('input[name=firstname]').type(firstName);
  cy.get('input[name=lastname]').type(lastName);
  cy.get('input[name=email]').clear();
  cy.get('input[name=email]').type(email);
  cy.get('input[name=year]').clear();
  cy.get('input[name=year]').type(year);
  cy.get('input[name=month]').clear();
  cy.get('input[name=month]').type(month);
  cy.get('input[name=day]').clear();
  cy.get('input[name=day]').type(day);
  cy.get('[data-cy=submit-edit-employee-button]').click();
});

// Set the hired at date
Cypress.Commands.add('setHiredDate', (companyId = 1, employeeId = 1, year, month, day) => {
  cy.visit('/' + companyId + '/employees/' + employeeId);
  cy.get('[data-cy=edit-important-date-link]').click();
  cy.get('input[name=hired_at_year]').clear();
  cy.get('input[name=hired_at_year]').type(year);
  cy.get('input[name=hired_at_month]').clear();
  cy.get('input[name=hired_at_month]').type(month);
  cy.get('input[name=hired_at_day]').clear();
  cy.get('input[name=hired_at_day]').type(day);
  cy.get('[data-cy=submit-edit-employee-button]').click();
});

// Set the Twitter account
Cypress.Commands.add('setTwitterAccount', (companyId = 1, employeeId = 1, twitterAccount = '') => {
  cy.visit('/' + companyId + '/employees/' + employeeId);
  cy.get('[data-cy=edit-important-date-link]').click();
  cy.get('input[name=twitter]').clear();

  if (twitterAccount != '') {
    cy.get('input[name=twitter]').type(twitterAccount);
  }
  cy.get('[data-cy=submit-edit-employee-button]').click();
});

// Set the Slack account
Cypress.Commands.add('setSlackAccount', (companyId = 1, employeeId = 1, slackAccount = '') => {
  cy.visit('/' + companyId + '/employees/' + employeeId);
  cy.get('[data-cy=edit-important-date-link]').click();
  cy.get('input[name=slack]').clear();

  if (slackAccount != '') {
    cy.get('input[name=slack]').type(slackAccount);
  }
  cy.get('[data-cy=submit-edit-employee-button]').click();
});

// Set an address
Cypress.Commands.add('setAddress', (companyId = 1, employeeId = 1, street = '612 St Jacques St', city = 'Montreal', state = 'QC', postalCode = 'H3C 4M8') => {
  cy.visit('/' + companyId + '/employees/' + employeeId);
  cy.get('[data-cy=edit-important-date-link]').click();
  cy.get('[data-cy=menu-address-link]').click();

  cy.get('input[name=street]').clear();
  cy.get('input[name=street]').type(street);
  cy.get('input[name=city]').clear();
  cy.get('input[name=city]').type(city);
  cy.get('input[name=state]').clear();
  cy.get('input[name=state]').type(state);
  cy.get('input[name=postal_code]').clear();
  cy.get('input[name=postal_code]').type(postalCode);
  cy.get('[data-cy=country_selector]').click();
  cy.get('ul.vs__dropdown-menu>li').eq(3).click();
  cy.get('[data-cy=country_selector]').click();
  cy.get('input[name=state]').click();
  cy.get('[data-cy=submit-edit-employee-button]').click();
});

// Toggle the eCoffee process in the company
Cypress.Commands.add('toggleECoffeeProcesss', (companyId = 1, enable = true) => {
  cy.visit('/' + companyId + '/account/ecoffee');

  if (enable == true) {
    cy.get('[data-cy=enable-ecoffee-process]').click();
    cy.exec('php artisan ecoffee:start');
  } else {
    cy.get('[data-cy=disable-ecoffee-process]').click();
  }
});
