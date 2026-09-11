package com.jiraws.library.configuration;

import com.jiraws.library.book.Entities.Exception.BookCreationException;
import org.apache.coyote.BadRequestException;
import org.springframework.http.HttpStatus;
import org.springframework.http.ProblemDetail;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.ExceptionHandler;
import org.springframework.web.bind.annotation.RestControllerAdvice;

@RestControllerAdvice
public class GlobalExceptionHandler {
  @ExceptionHandler(Throwable.class)
  public ResponseEntity<ProblemDetail> catchAny(Throwable ex){
      ProblemDetail pd = ProblemDetail.forStatusAndDetail(HttpStatus.INTERNAL_SERVER_ERROR, ex.getMessage());
              return ResponseEntity.status(
                      HttpStatus.INTERNAL_SERVER_ERROR
              ).body(pd);
  }

    @ExceptionHandler(BookCreationException.class)
    public ResponseEntity<ProblemDetail> catchBadResquest(BookCreationException ex){
        ProblemDetail pd = ProblemDetail.forStatusAndDetail(HttpStatus.BAD_REQUEST, ex.getMessage());
        return ResponseEntity.status(
                HttpStatus.BAD_REQUEST
        ).body(pd);
    }
}
