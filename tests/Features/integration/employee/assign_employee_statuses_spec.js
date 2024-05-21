let faker = require('faker');

describe('Employee - Assign employee statuses', function () {
  it('should assign an employee status and remove it as administrator', function () {
    cy.loginLegacy();

    cy.createCompany();

    cy.createEmployeeStatus(1, 'Dunder Mifflin', false);

    cy.visit('/1/employees/1');

    // Open the modal
    cy.get('[data-cy=edit-status-button]').click();
    cy.get('[data-cy=list-status-1]').click();
    cy.get('[data-cy=status-name-right-permission]').contains('Dunder Mifflin');
    cy.hasAuditLog('Assigned the employee status called Dunder Mifflin', '/1/employees/1');
    cy.hasEmployeeLog('Assigned the employee status called Dunder Mifflin.', '/1/employees/1');

    // Open the modal to remove the assignment
    cy.get('[data-cy=edit-status-button').click();
    cy.get('[data-cy=status-reset-button]').click();
    cy.get('[data-cy=edit-status-button]').should('not.contain', 'Dunder Mifflin');
    cy.hasAuditLog('Removed the employee status called Dunder Mifflin from', '/1/employees/1');
    cy.hasEmployeeLog('Removed the employee status called Dunder Mifflin', '/1/employees/1');
  });

  it('should assign an employee status and remove it as hr', function () {
    cy.loginLegacy();

    cy.createCompany();

    cy.createEmployeeStatus(1, 'Dunder Mifflin', false);

    cy.get('body').invoke('attr', 'data-account-id').then(function (userId) {
      cy.changePermission(userId, 200);
    });
    cy.visit('/1/employees/1');

    // Open the modal
    cy.get('[data-cy=edit-status-button]').click();
    cy.get('[data-cy=list-status-1]').click();
    cy.get('[data-cy=status-name-right-permission]').contains('Dunder Mifflin');
    cy.hasEmployeeLog('Assigned the employee status called Dunder Mifflin.', '/1/employees/1');

    // Open the modal to remove the assignment
    cy.get('[data-cy=edit-status-button').click();
    cy.get('[data-cy=status-reset-button]').click();
    cy.get('[data-cy=edit-status-button]').should('not.contain', 'Dunder Mifflin');
    cy.hasEmployeeLog('Removed the employee status called Dunder Mifflin', '/1/employees/1');
  });

  it('should not let a normal user assign employee status', function () {
    cy.login((userId) => {
      cy.createCompany((companyId) => {

        var firstname = faker.name.firstName();
        var lastname = faker.name.lastName();
        cy.createEmployee(firstname, lastname, faker.internet.email(), 'user', true, (id) => {

          cy.changePermission(userId, 300);
          cy.visit(`/${companyId}/employees/${id}`);

          cy.contains('No status set');
          cy.get('[data-cy=edit-status-button]').should('not.exist');
        });
      });
    });
  });
});
