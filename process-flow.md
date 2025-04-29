# StarCoin Savings Order Processing Flow

## Complete Order Processing Flowchart

```mermaid
graph TD
    A[Customer Places Order] --> B[Store Gold Amount]
    B --> C[Initial Fraud Check]
    
    C -->|Pass| D[Status: Processing]
    C -->|Fail| E[Status: Blocked]
    C -->|Needs Review| F[Status: Review Required]
    
    D --> G[Check Fraud Check Meta]
    G -->|Yes| H[Call StarMaker API]
    G -->|No| I[Wait for Manual Review]
    
    H --> J[API Response]
    
    J -->|Code 0: Success| K[Status: Completed]
    J -->|Code 1: Failed| L[Status: Failed]
    J -->|Code 151: Risk Control| M[Status: On-Hold]
    
    K --> N[Gold Delivered]
    L --> O[Manual Review Needed]
    M --> P[Wait for Risk Clearance]
```

## Detailed Process Steps

### 1. Order Creation & Initial Processing
```mermaid
graph LR
    A[Order Created] --> B[Store Gold Amount]
    B --> C[Initial Fraud Check]
    C --> D[Set Initial Status]
```

### 2. Fraud Check Process
```mermaid
graph TD
    A[Fraud Check] -->|Pass| B[Status: Processing]
    A -->|Fail| C[Status: Blocked]
    A -->|Review| D[Status: Review Required]
    
    B --> E[Check _passed_fraud_check]
    E -->|Yes| F[Proceed to API]
    E -->|No| G[Wait for Review]
```

### 3. StarMaker API Integration
```mermaid
graph LR
    A[Prepare API Request] --> B[Send to StarMaker]
    B --> C[Receive Response]
    C --> D[Update Order Status]
    
    D -->|Success| E[Completed]
    D -->|Failed| F[Failed]
    D -->|Pending| G[On-Hold]
```

### 4. Status Update Flow
```mermaid
graph TD
    A[API Response] -->|Code 0| B[Status: Completed]
    A -->|Code 1| C[Status: Failed]
    A -->|Code 151| D[Status: On-Hold]
    
    B --> E[Gold Delivered]
    C --> F[Manual Review]
    D --> G[Risk Control]
```

## Key Status Transitions

1. **Successful Flow**
```
Pending → Processing → Completed
```

2. **Failed Flow**
```
Pending → Blocked
```

3. **Review Flow**
```
Pending → Review Required → Processing → Completed
```

4. **Risk Control Flow**
```
Pending → Processing → On-Hold → Completed
```

## Error Handling Flow

```mermaid
graph TD
    A[Error Detected] --> B{Error Type}
    
    B -->|API Connection| C[Retry 3 Times]
    B -->|Invalid Data| D[Mark as Failed]
    B -->|Risk Control| E[Set On-Hold]
    
    C -->|Success| F[Continue Process]
    C -->|Fail| G[Mark as Failed]
    
    D --> H[Manual Review]
    E --> I[Wait for Clearance]
```

## Testing Flow

```mermaid
graph TD
    A[Start Test] --> B[Create Test Order]
    B --> C[Monitor Status Changes]
    C --> D[Verify API Call]
    D --> E[Check Gold Delivery]
    
    E -->|Success| F[Test Passed]
    E -->|Failed| G[Debug Issue]
    
    G --> H[Check Logs]
    H --> I[Fix Issue]
    I --> B
```

## Important Notes

1. **Status Definitions**
   - `Pending`: Initial order state
   - `Processing`: Passed fraud check, being processed
   - `Completed`: Successfully delivered
   - `Failed`: Order failed
   - `Blocked`: Failed fraud check
   - `Review Required`: Needs manual review
   - `On-Hold`: Risk control pending

2. **Key Checkpoints**
   - Gold amount stored in order meta
   - Fraud check passed flag
   - API response validation
   - Status update confirmation

3. **Error Points**
   - Invalid StarMaker ID
   - API connection issues
   - Risk control blocks
   - Invalid IP address

4. **Success Criteria**
   - Order status reaches "Completed"
   - Gold delivered to StarMaker ID
   - All meta data properly stored
   - No errors in logs 